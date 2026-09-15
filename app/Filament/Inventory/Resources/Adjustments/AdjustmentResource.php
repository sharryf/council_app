<?php

namespace App\Filament\Inventory\Resources\Adjustments;

use App\Enums\InventoryAdjustmentStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventorySourceType;
use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\Adjustments\Pages\EditAdjustment;
use App\Filament\Inventory\Resources\Adjustments\Pages\ListAdjustments;
use App\Filament\Inventory\Resources\Adjustments\Pages\ViewAdjustment;
use App\Filament\Inventory\Resources\Adjustments\Schemas\AdjustmentForm;
use App\Filament\Inventory\Resources\Adjustments\Schemas\AdjustmentInfolist;
use App\Filament\Inventory\Resources\Adjustments\Tables\AdjustmentsTable;
use App\Models\InventoryAuditLog;
use App\Models\InventoryItemStock;
use App\Models\InventorySetting;
use App\Models\InventoryStockAdjustment;
use App\Models\InventoryStockMovement;
use App\Models\User;
use App\Services\Inventory\DocumentLock;
use App\Services\Inventory\InventorySequenceService;
use App\Services\Inventory\StockMovementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Spec 8.4 — the last of the ledger's three write documents (after
 * GRN/Stock In and Issue Requests/Stock Out). Every adjustment always
 * requires approval, and approving IS posting — there is no separate
 * manual post step: the creator only creates and submits, and the
 * approver's single action both signs off and immediately writes the
 * stock movements. Segregation of duties: the creator can never also
 * be the approver.
 */
class AdjustmentResource extends Resource
{
    use HasInventoryRoleAccess;

    protected static ?string $model = InventoryStockAdjustment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Adjustments';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'adjustment';

    protected static ?string $pluralModelLabel = 'adjustments';

    /**
     * Stock Admin or Approver (or above) — a plain User has no reason
     * to see adjustments, but Approvers still need this to review and
     * act on ones pending their approval.
     */
    public static function canAccess(): bool
    {
        return self::userIsStockAdminOrAbove() || self::userIsApproverOrAbove();
    }

    public static function canCreate(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    /**
     * BR-23-style immutability (same rule GRN/Issue Requests already
     * follow): editable only while Draft, never once it's entered the
     * approval queue or been posted.
     */
    public static function canEdit(Model $record): bool
    {
        /** @var InventoryStockAdjustment $record */
        return self::userIsStockAdminOrAbove() && $record->status === InventoryAdjustmentStatus::Draft;
    }

    public static function canDelete(Model $record): bool
    {
        /** @var InventoryStockAdjustment $record */
        return self::userIsStockAdminOrAbove() && $record->status === InventoryAdjustmentStatus::Draft;
    }

    public static function form(Schema $schema): Schema
    {
        return AdjustmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdjustmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdjustmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdjustments::route('/'),
            'view' => ViewAdjustment::route('/{record}'),
            'edit' => EditAdjustment::route('/{record}/edit'),
        ];
    }

    /**
     * Reused by the list page's header action and the empty-state
     * action — same pattern as IssueRequestResource::createAction(),
     * GoodsReceiptResource::createAction() and
     * ReturnResource::createAction(). No 'create' page route exists, so
     * CreateAction renders as a modal instead of navigating away.
     */
    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->createAnother(false)
            ->mutateFormDataUsing(function (array $data): array {
                $data['adjustment_no'] = app(InventorySequenceService::class)->next('ADJ');
                $data['status'] = InventoryAdjustmentStatus::Draft;
                $data['created_by'] = auth()->id();

                return $data;
            })
            ->successRedirectUrl(fn (InventoryStockAdjustment $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function submitForApprovalAction(): Action
    {
        return Action::make('submitForApproval')
            ->label('Submit for Approval')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (InventoryStockAdjustment $record): bool => self::userIsStockAdminOrAbove()
                && $record->status === InventoryAdjustmentStatus::Draft)
            ->action(function (InventoryStockAdjustment $record): void {
                DocumentLock::once("adjustment:{$record->id}", function () use ($record) {
                    $record->refresh();

                    if ($record->status !== InventoryAdjustmentStatus::Draft) {
                        return;
                    }

                    $record->loadMissing('lines');

                    if ($record->lines->where('difference_qty', '!=', 0)->isEmpty()) {
                        Notification::make()->title('At least one line must have a non-zero difference before submitting.')->danger()->send();

                        return;
                    }

                    $record->update(['status' => InventoryAdjustmentStatus::PendingApproval]);

                    InventoryAuditLog::write('ADJUSTMENT', $record->id, 'SUBMIT_FOR_APPROVAL', ['status' => 'DRAFT'], ['status' => 'PENDING_APPROVAL']);

                    app(\App\Services\Inventory\InventoryNotifier::class)->adjustmentPendingApproval($record->fresh());

                    Notification::make()->title("{$record->adjustment_no} submitted for approval.")->success()->send();
                });
            });
    }

    /**
     * Approving IS posting — there is no separate manual post step.
     * Segregation of duties: whoever created the adjustment must not be
     * the one who approves (and thereby posts) it, re-checked
     * server-side and not just via the button's own visible(). Posts
     * every non-zero-difference line (spec 8.4 step 4 — zero-diff lines
     * are ignored), positive difference -> AdjustIn, negative ->
     * AdjustOut, and stamps last_counted_at on every touched stock row.
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('This approves and immediately posts the adjustment — posted adjustments cannot be edited, only reversed.')
            ->visible(fn (InventoryStockAdjustment $record): bool => self::userIsAdmin()
                && $record->status === InventoryAdjustmentStatus::PendingApproval
                && $record->created_by !== auth()->id())
            ->action(function (InventoryStockAdjustment $record): void {
                DocumentLock::once("adjustment:{$record->id}", function () use ($record) {
                    $record->refresh();

                    if ($record->status !== InventoryAdjustmentStatus::PendingApproval) {
                        return;
                    }

                    if ($record->created_by !== null && $record->created_by === auth()->id()) {
                        Notification::make()->title('A different person must approve this adjustment than the one who created it.')->danger()->send();

                        return;
                    }

                    $record->loadMissing(['lines.item', 'location']);
                    $lines = $record->lines->filter(fn ($line) => bccomp((string) $line->difference_qty, '0', 3) !== 0);

                    if ($lines->isEmpty()) {
                        Notification::make()->title('No lines with a non-zero difference to post.')->danger()->send();

                        return;
                    }

                    $inactiveItem = $lines->first(fn ($line) => ! $line->item->is_active);
                    if ($inactiveItem) {
                        Notification::make()->title("{$inactiveItem->item->code} is inactive and cannot be adjusted.")->danger()->send();

                        return;
                    }

                    $lockDate = InventorySetting::get('posting_lock_date');
                    if (filled($lockDate) && $record->adjustment_date->lt(\Illuminate\Support\Carbon::parse($lockDate))) {
                        Notification::make()->title("Adjustment date is before the posting lock date ({$lockDate}).")->danger()->send();

                        return;
                    }

                    $performer = auth()->user();
                    /** @var User $performer */
                    $movements = app(StockMovementService::class);

                    try {
                        DB::transaction(function () use ($record, $lines, $movements, $performer) {
                            foreach ($lines->sortBy('item_id') as $line) {
                                $diff = (string) $line->difference_qty;
                                $type = bccomp($diff, '0', 3) > 0 ? InventoryMovementType::AdjustIn : InventoryMovementType::AdjustOut;

                                $movements->record(
                                    item: $line->item,
                                    location: $record->location,
                                    type: $type,
                                    quantity: ltrim($diff, '-'),
                                    performer: $performer,
                                    sourceType: InventorySourceType::Adjustment,
                                    sourceId: $record->id,
                                    sourceLineId: $line->id,
                                    sourceNo: $record->adjustment_no,
                                    remarks: $record->reason,
                                );

                                InventoryItemStock::query()
                                    ->where('item_id', $line->item_id)
                                    ->where('location_id', $record->location_id)
                                    ->update(['last_counted_at' => now()]);
                            }

                            $record->update([
                                'status' => InventoryAdjustmentStatus::Posted,
                                'approved_by' => $performer->id,
                                'approved_at' => now(),
                                'posted_by' => $performer->id,
                                'posted_at' => now(),
                            ]);
                        });
                    } catch (\InvalidArgumentException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    }

                    InventoryAuditLog::write('ADJUSTMENT', $record->id, 'APPROVE', ['status' => 'PENDING_APPROVAL'], ['status' => 'POSTED', 'approved_by' => $performer->id]);

                    Notification::make()->title("{$record->adjustment_no} approved and posted.")->success()->send();
                });
            });
    }

    /**
     * Admin-only, and — same segregation-of-duties rule as
     * approveAction() — never the person who created the adjustment;
     * cancelAction() below is that person's own equivalent instead.
     */
    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (InventoryStockAdjustment $record): bool => self::userIsAdmin()
                && $record->status === InventoryAdjustmentStatus::PendingApproval
                && $record->created_by !== auth()->id())
            ->schema([
                Textarea::make('reason')->label('Rejection reason')->required()->rows(2),
            ])
            ->action(function (InventoryStockAdjustment $record, array $data): void {
                DocumentLock::once("adjustment:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if ($record->status !== InventoryAdjustmentStatus::PendingApproval) {
                        return;
                    }

                    if ($record->created_by !== null && $record->created_by === auth()->id()) {
                        Notification::make()->title('A different person must reject this adjustment than the one who created it.')->danger()->send();

                        return;
                    }

                    $record->update([
                        'status' => InventoryAdjustmentStatus::Draft,
                        'reason' => $record->reason."\n\nRejected: {$data['reason']}",
                    ]);

                    InventoryAuditLog::write('ADJUSTMENT', $record->id, 'REJECT', ['status' => 'PENDING_APPROVAL'], ['status' => 'DRAFT', 'reason' => $data['reason']]);

                    Notification::make()->title("{$record->adjustment_no} returned to draft.")->warning()->send();
                });
            });
    }

    /**
     * The creator's own equivalent of rejectAction() — withdrawing a
     * submission you're not allowed to approve yourself isn't a
     * "rejection" (that implies someone else's decision), so it's a
     * separate, lighter-weight action: no reason required, since you're
     * not being told why your own submission was declined.
     */
    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('This withdraws the adjustment back to Draft so you can edit or resubmit it.')
            ->visible(fn (InventoryStockAdjustment $record): bool => $record->status === InventoryAdjustmentStatus::PendingApproval
                && $record->created_by === auth()->id())
            ->action(function (InventoryStockAdjustment $record): void {
                DocumentLock::once("adjustment:{$record->id}", function () use ($record) {
                    $record->refresh();

                    if ($record->status !== InventoryAdjustmentStatus::PendingApproval) {
                        return;
                    }

                    if ($record->created_by !== auth()->id()) {
                        Notification::make()->title('Only the person who created this adjustment can cancel it.')->danger()->send();

                        return;
                    }

                    $record->update(['status' => InventoryAdjustmentStatus::Draft]);

                    InventoryAuditLog::write('ADJUSTMENT', $record->id, 'CANCEL', ['status' => 'PENDING_APPROVAL'], ['status' => 'DRAFT']);

                    Notification::make()->title("{$record->adjustment_no} cancelled and returned to draft.")->warning()->send();
                });
            });
    }

    public static function reverseAction(): Action
    {
        return Action::make('reverse')
            ->label('Reverse')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (InventoryStockAdjustment $record): bool => self::userIsStockAdminOrAbove() && $record->status === InventoryAdjustmentStatus::Posted)
            ->schema([
                Textarea::make('reason')->label('Reversal reason')->required(),
            ])
            ->action(function (InventoryStockAdjustment $record, array $data): void {
                DocumentLock::once("adjustment:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if ($record->status !== InventoryAdjustmentStatus::Posted) {
                        return;
                    }

                    $performer = auth()->user();
                    /** @var User $performer */
                    $movements = app(StockMovementService::class);

                    try {
                        DB::transaction(function () use ($record, $movements, $performer, $data) {
                            $lineMovements = InventoryStockMovement::query()
                                ->where('source_type', InventorySourceType::Adjustment)
                                ->where('source_id', $record->id)
                                ->get();

                            foreach ($lineMovements as $movement) {
                                $movements->reverse($movement, $performer, $data['reason']);
                            }

                            $record->update(['status' => InventoryAdjustmentStatus::Reversed]);
                        });
                    } catch (\InvalidArgumentException $exception) {
                        Notification::make()->title("Cannot reverse: {$exception->getMessage()}")->danger()->send();

                        return;
                    }

                    InventoryAuditLog::write('ADJUSTMENT', $record->id, 'REVERSE', ['status' => 'POSTED'], ['status' => 'REVERSED', 'reason' => $data['reason']]);

                    Notification::make()->title("{$record->adjustment_no} reversed.")->warning()->send();
                });
            });
    }
}
