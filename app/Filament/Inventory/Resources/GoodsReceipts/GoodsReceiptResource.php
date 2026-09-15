<?php

namespace App\Filament\Inventory\Resources\GoodsReceipts;

use App\Enums\InventoryGoodsReceiptStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventorySourceType;
use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\GoodsReceipts\Pages\EditGoodsReceipt;
use App\Filament\Inventory\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Filament\Inventory\Resources\GoodsReceipts\Pages\ViewGoodsReceipt;
use App\Filament\Inventory\Resources\GoodsReceipts\Schemas\GoodsReceiptForm;
use App\Filament\Inventory\Resources\GoodsReceipts\Schemas\GoodsReceiptInfolist;
use App\Filament\Inventory\Resources\GoodsReceipts\Tables\GoodsReceiptsTable;
use App\Models\InventoryAttachment;
use App\Models\InventoryAuditLog;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventorySetting;
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
use Illuminate\Support\Facades\Storage;

class GoodsReceiptResource extends Resource
{
    use HasInventoryRoleAccess;

    protected static ?string $model = InventoryGoodsReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Stock In';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'goods receipt';

    protected static ?string $pluralModelLabel = 'goods receipts';

    /**
     * Stock Admin or above — a plain User has no reason to see receiving
     * (they only ever raise/track their own Issue Requests, per the
     * module's own role design).
     */
    public static function canAccess(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    public static function canCreate(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    /**
     * BR-23: a posted GRN is immutable — editable only while Draft.
     */
    public static function canEdit(Model $record): bool
    {
        /** @var InventoryGoodsReceipt $record */
        return self::userIsStockAdminOrAbove() && $record->status === InventoryGoodsReceiptStatus::Draft;
    }

    public static function canDelete(Model $record): bool
    {
        /** @var InventoryGoodsReceipt $record */
        return self::userIsStockAdminOrAbove() && $record->status === InventoryGoodsReceiptStatus::Draft;
    }

    public static function form(Schema $schema): Schema
    {
        return GoodsReceiptForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return GoodsReceiptInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GoodsReceiptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGoodsReceipts::route('/'),
            'view' => ViewGoodsReceipt::route('/{record}'),
            'edit' => EditGoodsReceipt::route('/{record}/edit'),
        ];
    }

    /**
     * Reused by the list page's header action and the empty-state
     * action — same pattern as IssueRequestResource::createAction(). No
     * 'create' page route exists, so CreateAction renders as a modal
     * instead of navigating away.
     */
    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->createAnother(false)
            ->mutateFormDataUsing(function (array $data): array {
                $data['grn_no'] = app(InventorySequenceService::class)->next('GRN');
                $data['status'] = InventoryGoodsReceiptStatus::Draft;
                $data['created_by'] = auth()->id();

                return $data;
            })
            ->using(function (array $data): InventoryGoodsReceipt {
                // Not a column on this model — see
                // InventoryGoodsReceipt::attachments(); captured here and
                // turned into inventory_attachments rows below.
                $attachmentPaths = $data['attachments'] ?? [];
                unset($data['attachments']);

                $record = InventoryGoodsReceipt::create($data);

                foreach ($attachmentPaths as $path) {
                    InventoryAttachment::create([
                        'entity_type' => 'GRN',
                        'entity_id' => $record->id,
                        'file_name' => basename($path),
                        'file_path' => $path,
                        'file_size' => Storage::disk('local')->size($path),
                        'mime_type' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream',
                        'uploaded_by' => auth()->id(),
                        'uploaded_at' => now(),
                    ]);
                }

                return $record;
            })
            ->successRedirectUrl(fn (InventoryGoodsReceipt $record): string => static::getUrl('view', ['record' => $record]));
    }

    /**
     * Reused by the table row action and the View/Edit page header —
     * same pattern as MeetingResource::approveAction(). Validates
     * everything up front (spec 8.1) before touching any stock, then
     * posts every line inside one transaction, ascending by item_id
     * (Phase 3's deadlock-avoidance guidance).
     */
    public static function postAction(): Action
    {
        return Action::make('post')
            ->label('Post')
            ->icon(Heroicon::OutlinedCheck)
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('Posted receipts cannot be edited, only reversed.')
            ->visible(fn (InventoryGoodsReceipt $record): bool => self::userIsStockAdminOrAbove() && $record->status === InventoryGoodsReceiptStatus::Draft)
            ->action(function (InventoryGoodsReceipt $record): void {
                DocumentLock::once("grn:{$record->id}", function () use ($record) {
                    $record->refresh();

                    if ($record->status !== InventoryGoodsReceiptStatus::Draft) {
                        return;
                    }

                    $record->loadMissing('lines.item');

                    if ($record->lines->isEmpty()) {
                        Notification::make()->title('Add at least one line before posting.')->danger()->send();

                        return;
                    }

                    $inactiveItem = $record->lines->first(fn ($line) => ! $line->item->is_active);
                    if ($inactiveItem) {
                        Notification::make()->title("{$inactiveItem->item->code} is inactive and cannot be received.")->danger()->send();

                        return;
                    }

                    // BR-26: an invoice reference is required unless the
                    // setting says otherwise.
                    if (InventorySetting::getBool('require_receipt_reference', true) && blank($record->invoice_no)) {
                        Notification::make()->title('Enter an invoice number before posting.')->danger()->send();

                        return;
                    }

                    // BR-27: no document may post before posting_lock_date.
                    $lockDate = InventorySetting::get('posting_lock_date');
                    if (filled($lockDate) && $record->receipt_date->lt(\Illuminate\Support\Carbon::parse($lockDate))) {
                        Notification::make()->title("Receipt date is before the posting lock date ({$lockDate}).")->danger()->send();

                        return;
                    }

                    $performer = auth()->user();
                    /** @var User $performer */
                    $movements = app(StockMovementService::class);

                    try {
                        DB::transaction(function () use ($record, $movements, $performer) {
                            foreach ($record->lines->sortBy('item_id') as $line) {
                                $movements->record(
                                    item: $line->item,
                                    location: $record->location,
                                    type: InventoryMovementType::Receipt,
                                    quantity: (string) $line->quantity,
                                    performer: $performer,
                                    sourceType: InventorySourceType::Grn,
                                    sourceId: $record->id,
                                    sourceLineId: $line->id,
                                    sourceNo: $record->grn_no,
                                    reference: $record->invoice_no,
                                );

                                $line->item->update([
                                    'last_received_date' => $record->receipt_date,
                                    'default_supplier_id' => $line->item->default_supplier_id ?? $record->supplier_id,
                                ]);
                            }

                            $record->update([
                                'status' => InventoryGoodsReceiptStatus::Posted,
                                'posted_by' => $performer->id,
                                'posted_at' => now(),
                            ]);
                        });
                    } catch (\InvalidArgumentException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    }

                    InventoryAuditLog::write('GRN', $record->id, 'POST', ['status' => 'DRAFT'], ['status' => 'POSTED']);

                    Notification::make()->title("{$record->grn_no} posted.")->success()->send();
                });
            });
    }

    /**
     * Damage/Loss/Correction-style scrutiny (spec 8.4's own "the gate
     * is the type") extended to GRN reversal — this is the one place a
     * posted, immutable document's effect on stock gets undone, so it
     * gets the same "someone else signs off" treatment as a write-off
     * adjustment, gated by its own setting rather than hardcoded on.
     */
    public static function reversalNeedsApproval(): bool
    {
        return InventorySetting::getBool('require_approval_for_grn_reversal', true);
    }

    /**
     * Posted -> PendingReversal (or straight to Reversed if the setting
     * is off, preserving the old one-step behaviour) — never touches
     * stock itself; only approveReversalAction() does that.
     */
    public static function requestReversalAction(): Action
    {
        return Action::make('requestReversal')
            ->label('Reverse')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (InventoryGoodsReceipt $record): bool => self::userIsStockAdminOrAbove() && $record->status === InventoryGoodsReceiptStatus::Posted)
            ->schema([
                Textarea::make('reason')
                    ->label('Reversal reason')
                    ->required(),
            ])
            ->action(function (InventoryGoodsReceipt $record, array $data): void {
                DocumentLock::once("grn:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if ($record->status !== InventoryGoodsReceiptStatus::Posted) {
                        return;
                    }

                    if (! self::reversalNeedsApproval()) {
                        self::performReversal($record, $data['reason'], auth()->user());

                        return;
                    }

                    $record->update([
                        'status' => InventoryGoodsReceiptStatus::PendingReversal,
                        'reversal_reason' => $data['reason'],
                    ]);

                    InventoryAuditLog::write('GRN', $record->id, 'REQUEST_REVERSAL', ['status' => 'POSTED'], ['status' => 'PENDING_REVERSAL', 'reason' => $data['reason']]);

                    app(\App\Services\Inventory\InventoryNotifier::class)->grnPendingReversalApproval($record->fresh());

                    Notification::make()->title("{$record->grn_no} submitted for reversal approval.")->success()->send();
                });
            });
    }

    public static function approveReversalAction(): Action
    {
        return Action::make('approveReversal')
            ->label('Approve Reversal')
            ->icon(Heroicon::OutlinedCheck)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This will undo the stock this receipt brought in.')
            ->visible(fn (InventoryGoodsReceipt $record): bool => self::userIsAdmin() && $record->status === InventoryGoodsReceiptStatus::PendingReversal)
            ->action(function (InventoryGoodsReceipt $record): void {
                DocumentLock::once("grn:{$record->id}", function () use ($record) {
                    $record->refresh();

                    if ($record->status !== InventoryGoodsReceiptStatus::PendingReversal) {
                        return;
                    }

                    self::performReversal($record, $record->reversal_reason, auth()->user());
                });
            });
    }

    public static function rejectReversalAction(): Action
    {
        return Action::make('rejectReversal')
            ->label('Reject Reversal')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (InventoryGoodsReceipt $record): bool => self::userIsAdmin() && $record->status === InventoryGoodsReceiptStatus::PendingReversal)
            ->schema([
                Textarea::make('reason')->label('Rejection reason')->required()->rows(2),
            ])
            ->action(function (InventoryGoodsReceipt $record, array $data): void {
                DocumentLock::once("grn:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if ($record->status !== InventoryGoodsReceiptStatus::PendingReversal) {
                        return;
                    }

                    $record->update([
                        'status' => InventoryGoodsReceiptStatus::Posted,
                        'reversal_reason' => $record->reversal_reason."\n\nRejected: {$data['reason']}",
                    ]);

                    InventoryAuditLog::write('GRN', $record->id, 'REJECT_REVERSAL', ['status' => 'PENDING_REVERSAL'], ['status' => 'POSTED', 'reason' => $data['reason']]);

                    Notification::make()->title("{$record->grn_no} reversal rejected — back to Posted.")->warning()->send();
                });
            });
    }

    /**
     * The actual movement-reversing step, shared by the
     * no-approval-needed fast path in requestReversalAction() and
     * approveReversalAction() — reverses every line's movement inside
     * one transaction, so a mid-loop failure (one line would go
     * negative) rolls back everything already reversed in this call
     * rather than leaving the GRN half-reversed.
     */
    private static function performReversal(InventoryGoodsReceipt $record, ?string $reason, User $performer): void
    {
        $movements = app(StockMovementService::class);
        $fromStatus = $record->status;

        try {
            DB::transaction(function () use ($record, $movements, $performer, $reason) {
                foreach ($record->lines as $line) {
                    $movement = InventoryStockMovement::query()
                        ->where('source_type', InventorySourceType::Grn)
                        ->where('source_id', $record->id)
                        ->where('source_line_id', $line->id)
                        ->sole();

                    $movements->reverse($movement, $performer, (string) $reason);
                }

                $record->update([
                    'status' => InventoryGoodsReceiptStatus::Reversed,
                    'reversed_by' => $performer->id,
                    'reversed_at' => now(),
                    'reversal_reason' => $reason,
                ]);
            });
        } catch (\InvalidArgumentException $exception) {
            Notification::make()->title("Cannot reverse: {$exception->getMessage()}")->danger()->send();

            return;
        }

        InventoryAuditLog::write('GRN', $record->id, 'REVERSE', ['status' => $fromStatus->value], ['status' => 'REVERSED', 'reason' => $reason]);

        Notification::make()->title("{$record->grn_no} reversed.")->warning()->send();
    }
}
