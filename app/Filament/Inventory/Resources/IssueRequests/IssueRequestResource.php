<?php

namespace App\Filament\Inventory\Resources\IssueRequests;

use App\Enums\InventoryIssueRequestLineStatus;
use App\Enums\InventoryIssueRequestStatus;
use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\IssueRequests\Pages\EditIssueRequest;
use App\Filament\Inventory\Resources\IssueRequests\Pages\IssueGoods;
use App\Filament\Inventory\Resources\IssueRequests\Pages\ListIssueRequests;
use App\Filament\Inventory\Resources\IssueRequests\Pages\ViewIssueRequest;
use App\Filament\Inventory\Resources\IssueRequests\Schemas\IssueRequestForm;
use App\Filament\Inventory\Resources\IssueRequests\Schemas\IssueRequestInfolist;
use App\Filament\Inventory\Resources\IssueRequests\Tables\IssueRequestsTable;
use App\Models\InventoryIssueRequest;
use App\Models\InventorySetting;
use App\Models\User;
use App\Services\Inventory\DocumentLock;
use App\Services\Inventory\InventoryNotifier;
use App\Services\Inventory\InventorySequenceService;
use App\Services\Inventory\StockMovementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use InvalidArgumentException;

class IssueRequestResource extends Resource
{
    use HasInventoryRoleAccess;

    protected static ?string $model = InventoryIssueRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Stock Out';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'issue request';

    protected static ?string $pluralModelLabel = 'issue requests';

    public static function canAccess(): bool
    {
        return self::userHasAnyInventoryRole();
    }

    /**
     * Spec 5.2: every inventory role, including a plain User, may raise
     * a request — unlike Items/GRN this is not Stock-Admin-gated.
     */
    public static function canCreate(): bool
    {
        return self::userHasAnyInventoryRole();
    }

    /**
     * BR-20: editable only in Draft, and only by whoever raised it (or
     * an Admin) — a colleague with the User role can't edit someone
     * else's draft request just because they also hold that role.
     */
    public static function canEdit(Model $record): bool
    {
        /** @var InventoryIssueRequest $record */
        return $record->status === InventoryIssueRequestStatus::Draft && self::isOwnerOrAdmin($record);
    }

    public static function canDelete(Model $record): bool
    {
        /** @var InventoryIssueRequest $record */
        return $record->status === InventoryIssueRequestStatus::Draft && self::isOwnerOrAdmin($record);
    }

    private static function isOwnerOrAdmin(InventoryIssueRequest $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user && ($record->requested_by === $user->id || self::userIsStockAdminOrAbove());
    }

    /**
     * Spec BR-17: an approver may not act on their own request unless
     * the allow_self_approval setting says otherwise.
     */
    private static function canActOnApproval(InventoryIssueRequest $record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! self::userIsApproverOrAbove()) {
            return false;
        }

        if ($record->requested_by === $user->id && ! InventorySetting::getBool('allow_self_approval')) {
            return false;
        }

        return true;
    }

    /**
     * Approved/PartiallyIssued is the normal case; a Submitted request
     * also qualifies when require_approval_for_issue is off — the
     * setting's own seeded description is "if false, stock admins issue
     * directly" (see IssueGoods, which treats that case as an implicit
     * full-quantity approval immediately before issuing).
     */
    public static function canIssue(InventoryIssueRequest $record): bool
    {
        if (! self::userIsStockAdminOrAbove()) {
            return false;
        }

        if (in_array($record->status, [InventoryIssueRequestStatus::Approved, InventoryIssueRequestStatus::PartiallyIssued], true)) {
            return true;
        }

        return $record->status === InventoryIssueRequestStatus::Submitted
            && ! InventorySetting::getBool('require_approval_for_issue', true);
    }

    public static function form(Schema $schema): Schema
    {
        return IssueRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IssueRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IssueRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIssueRequests::route('/'),
            'view' => ViewIssueRequest::route('/{record}'),
            'edit' => EditIssueRequest::route('/{record}/edit'),
            'issue' => IssueGoods::route('/{record}/issue'),
        ];
    }

    /**
     * A modal instead of a dedicated page — with no 'create' page route
     * registered above, CreateAction opens as a popup automatically.
     * The form itself (Issuing To/Purpose/Items/Remarks) is short
     * enough that a full page navigation was more ceremony than
     * raising a request (spec 5.2: every role may do this, often) needs.
     */
    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->createAnother(false)
            ->mutateFormDataUsing(function (array $data): array {
                $data['request_no'] = app(InventorySequenceService::class)->next('IR');
                $data['request_date'] = now();
                $data['status'] = InventoryIssueRequestStatus::Draft;
                $data['requested_by'] = auth()->id();

                return $data;
            })
            // Lands on the new (Draft) request's own page rather than
            // back on the list — Submit is right there as a header
            // action instead of needing a second click to find the row
            // and open it.
            ->successRedirectUrl(fn (InventoryIssueRequest $record): string => static::getUrl('view', ['record' => $record]));
    }

    /**
     * Draft -> Submitted. No approver is assigned here — the single
     * approver-pool decision (Phase 1) means approver_id is only ever
     * set to whichever Approver actually acts, not chosen up front.
     */
    public static function submitAction(): Action
    {
        return Action::make('submit')
            ->label('Submit')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (InventoryIssueRequest $record): bool => $record->status === InventoryIssueRequestStatus::Draft && self::isOwnerOrAdmin($record))
            ->action(function (InventoryIssueRequest $record): void {
                DocumentLock::once("issue-request:{$record->id}", function () use ($record) {
                    $record->refresh();

                    if ($record->status !== InventoryIssueRequestStatus::Draft) {
                        return;
                    }

                    $record->loadMissing('lines.item');

                    if ($record->lines->isEmpty()) {
                        Notification::make()->title('Add at least one line before submitting.')->danger()->send();

                        return;
                    }

                    $inactiveItem = $record->lines->first(fn ($line) => ! $line->item->is_active);
                    if ($inactiveItem) {
                        Notification::make()->title("{$inactiveItem->item->code} is inactive and cannot be requested.")->danger()->send();

                        return;
                    }

                    $performer = auth()->user();

                    DB::transaction(function () use ($record, $performer) {
                        $record->update([
                            'status' => InventoryIssueRequestStatus::Submitted,
                            'submitted_at' => now(),
                            'total_lines' => $record->lines->count(),
                            'total_qty' => $record->lines->sum('requested_qty'),
                        ]);

                        $record->approvalActions()->create([
                            'action' => 'SUBMITTED',
                            'action_by' => $performer->id,
                            'action_at' => now(),
                        ]);
                    });

                    app(InventoryNotifier::class)->requestSubmitted($record->fresh());

                    Notification::make()->title("{$record->request_no} submitted for approval.")->success()->send();
                });
            });
    }

    /**
     * Submitted -> Approved. Validates every line against current
     * availability atomically first — if any line's chosen quantity
     * exceeds what's available, nothing commits at all (spec 8.2 step
     * 3), then reserve()s each approved line inside the same
     * transaction.
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->visible(fn (InventoryIssueRequest $record): bool => $record->status === InventoryIssueRequestStatus::Submitted && self::canActOnApproval($record))
            ->schema(fn (InventoryIssueRequest $record): array => [
                Repeater::make('lines')
                    ->label('Lines')
                    ->default($record->lines()->with('item')->get()->map(fn ($line): array => [
                        'line_id' => $line->id,
                        'item_label' => "{$line->item->code} — {$line->item->name} ({$line->item->stock->sum('available')} available)",
                        'requested_qty' => Number::format((float) $line->requested_qty),
                        'approved_qty' => Number::format((float) $line->requested_qty),
                    ])->all())
                    ->table([
                        TableColumn::make('Item'),
                        TableColumn::make('Requested')->width('140px'),
                        TableColumn::make('Approve Qty')->markAsRequired()->width('160px'),
                    ])
                    ->schema([
                        Hidden::make('line_id'),
                        TextInput::make('item_label')->hiddenLabel()->disabled()->dehydrated(false),
                        TextInput::make('requested_qty')->hiddenLabel()->disabled()->dehydrated(false),
                        TextInput::make('approved_qty')
                            ->hiddenLabel()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(fn (Get $get) => $get('requested_qty'))
                            ->required(),
                    ])
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),
                Textarea::make('approval_remarks')->label('Remarks')->rows(2),
            ])
            ->action(function (InventoryIssueRequest $record, array $data): void {
                self::processApproval($record, $data);
            });
    }

    /**
     * The approve modal's schema is built from the record's own lines
     * (a per-line quantity field each), so it can't be a fixed array
     * literal the way the other actions' schemas are — kept separate
     * from approveAction() only so that method's ->schema() closure
     * stays readable.
     */
    private static function processApproval(InventoryIssueRequest $record, array $data): void
    {
        DocumentLock::once("issue-request:{$record->id}", function () use ($record, $data) {
            $record->refresh();

            if ($record->status !== InventoryIssueRequestStatus::Submitted) {
                return;
            }

            $record->loadMissing(['lines.item', 'location']);
            $performer = auth()->user();
            /** @var User $performer */
            $movements = app(StockMovementService::class);
            $approvedQuantities = collect($data['lines'] ?? [])->keyBy('line_id');

            try {
                DB::transaction(function () use ($record, $data, $performer, $movements, $approvedQuantities) {
                    foreach ($record->lines as $line) {
                        $approvedQty = (string) ($approvedQuantities->get($line->id)['approved_qty'] ?? $line->requested_qty);

                        if (bccomp($approvedQty, '0', 3) > 0) {
                            $movements->reserve($line->item, $record->location, $approvedQty);
                        }

                        $line->update([
                            'approved_qty' => $approvedQty,
                            'line_status' => bccomp($approvedQty, '0', 3) > 0
                                ? InventoryIssueRequestLineStatus::Approved
                                : InventoryIssueRequestLineStatus::Rejected,
                        ]);
                    }

                    $record->update([
                        'status' => InventoryIssueRequestStatus::Approved,
                        'approver_id' => $performer->id,
                        'approved_at' => now(),
                        'approval_remarks' => $data['approval_remarks'] ?? null,
                    ]);

                    $record->approvalActions()->create([
                        'action' => 'APPROVED',
                        'action_by' => $performer->id,
                        'action_at' => now(),
                        'remarks' => $data['approval_remarks'] ?? null,
                    ]);
                });
            } catch (InvalidArgumentException $exception) {
                Notification::make()->title($exception->getMessage())->danger()->send();

                return;
            }

            app(InventoryNotifier::class)->requestApproved($record->fresh());

            Notification::make()->title("{$record->request_no} approved.")->success()->send();
        });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (InventoryIssueRequest $record): bool => $record->status === InventoryIssueRequestStatus::Submitted && self::canActOnApproval($record))
            ->schema([
                Textarea::make('reason')
                    ->label('Rejection reason')
                    ->required()
                    ->minLength(10)
                    ->rows(2),
            ])
            ->action(function (InventoryIssueRequest $record, array $data): void {
                DocumentLock::once("issue-request:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if ($record->status !== InventoryIssueRequestStatus::Submitted) {
                        return;
                    }

                    $performer = auth()->user();
                    /** @var User $performer */

                    DB::transaction(function () use ($record, $data, $performer) {
                        $record->update([
                            'status' => InventoryIssueRequestStatus::Rejected,
                            'rejected_at' => now(),
                            'rejection_reason' => $data['reason'],
                        ]);

                        $record->approvalActions()->create([
                            'action' => 'REJECTED',
                            'action_by' => $performer->id,
                            'action_at' => now(),
                            'remarks' => $data['reason'],
                        ]);
                    });

                    app(InventoryNotifier::class)->requestRejected($record->fresh());

                    Notification::make()->title("{$record->request_no} rejected.")->warning()->send();
                });
            });
    }

    public static function returnForEditAction(): Action
    {
        return Action::make('returnForEdit')
            ->label('Return for Edit')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->visible(fn (InventoryIssueRequest $record): bool => $record->status === InventoryIssueRequestStatus::Submitted && self::canActOnApproval($record))
            ->schema([
                Textarea::make('reason')->label('Note to requester')->rows(2),
            ])
            ->action(function (InventoryIssueRequest $record, array $data): void {
                DocumentLock::once("issue-request:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if ($record->status !== InventoryIssueRequestStatus::Submitted) {
                        return;
                    }

                    $performer = auth()->user();
                    /** @var User $performer */

                    DB::transaction(function () use ($record, $data, $performer) {
                        $record->update(['status' => InventoryIssueRequestStatus::Draft, 'submitted_at' => null]);

                        $record->approvalActions()->create([
                            'action' => 'RETURNED_FOR_EDIT',
                            'action_by' => $performer->id,
                            'action_at' => now(),
                            'remarks' => $data['reason'] ?? null,
                        ]);
                    });

                    app(InventoryNotifier::class)->requestReturnedForEdit($record->fresh());

                    Notification::make()->title("{$record->request_no} returned to the requester.")->warning()->send();
                });
            });
    }

    /**
     * Draft/Submitted (by the requester) or Approved/PartiallyIssued (by
     * Stock Admin/Admin, releasing whatever reservation remains
     * outstanding) -> Cancelled.
     */
    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (InventoryIssueRequest $record): bool => self::canCancel($record))
            ->schema([
                Textarea::make('reason')->label('Cancellation reason')->required()->rows(2),
            ])
            ->action(function (InventoryIssueRequest $record, array $data): void {
                DocumentLock::once("issue-request:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if (! self::canCancel($record)) {
                        return;
                    }

                    $performer = auth()->user();
                    /** @var User $performer */
                    $movements = app(StockMovementService::class);

                    try {
                        DB::transaction(function () use ($record, $data, $performer, $movements) {
                            if (in_array($record->status, [InventoryIssueRequestStatus::Approved, InventoryIssueRequestStatus::PartiallyIssued], true)) {
                                $record->loadMissing(['lines.item', 'location']);

                                foreach ($record->lines as $line) {
                                    $outstanding = bcsub((string) ($line->approved_qty ?? '0'), (string) $line->issued_qty, 3);

                                    if (bccomp($outstanding, '0', 3) > 0) {
                                        $movements->release($line->item, $record->location, $outstanding);
                                    }
                                }
                            }

                            $record->update([
                                'status' => InventoryIssueRequestStatus::Cancelled,
                                'cancelled_at' => now(),
                                'cancelled_by' => $performer->id,
                                'cancellation_reason' => $data['reason'],
                            ]);

                            $record->approvalActions()->create([
                                'action' => 'CANCELLED',
                                'action_by' => $performer->id,
                                'action_at' => now(),
                                'remarks' => $data['reason'],
                            ]);
                        });
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title("{$record->request_no} cancelled.")->warning()->send();
                });
            });
    }

    private static function canCancel(InventoryIssueRequest $record): bool
    {
        if (in_array($record->status, [InventoryIssueRequestStatus::Draft, InventoryIssueRequestStatus::Submitted], true)) {
            return self::isOwnerOrAdmin($record);
        }

        if (in_array($record->status, [InventoryIssueRequestStatus::Approved, InventoryIssueRequestStatus::PartiallyIssued], true)) {
            return self::userIsStockAdminOrAbove();
        }

        return false;
    }

    /**
     * Links to the dedicated IssueGoods page rather than opening a
     * modal — same "complex enough to need its own Livewire page"
     * reasoning as Bureau's RecordMinutes (see
     * MeetingResource::recordMinutesAction()).
     */
    public static function issueAction(): Action
    {
        return Action::make('issue')
            ->label('Issue')
            ->icon(Heroicon::OutlinedTruck)
            ->color('primary')
            ->visible(fn (InventoryIssueRequest $record): bool => self::canIssue($record))
            ->url(fn (InventoryIssueRequest $record): string => static::getUrl('issue', ['record' => $record]));
    }
}
