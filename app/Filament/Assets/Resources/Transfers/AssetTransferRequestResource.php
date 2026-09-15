<?php

namespace App\Filament\Assets\Resources\Transfers;

use App\Enums\AssetTransferStatus;
use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\Transfers\Pages\ListAssetTransferRequests;
use App\Filament\Assets\Resources\Transfers\Tables\AssetTransferRequestsTable;
use App\Models\AssetHistory;
use App\Models\AssetTransferRequest;
use App\Services\Assets\AssetLock;
use App\Services\Assets\AssetNotifier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Read/decide-only — rows are created exclusively via
 * AssetResource::requestTransferAction() from the asset's own View
 * page, never through a generic create form here (spec section 5's
 * API shape: POST /transfers is that same action, not a standalone
 * resource form).
 */
class AssetTransferRequestResource extends Resource
{
    use HasAssetRoleAccess;

    protected static ?string $model = AssetTransferRequest::class;

    protected static ?string $slug = 'asset-transfers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Transfers';

    protected static ?string $modelLabel = 'transfer request';

    protected static ?string $pluralModelLabel = 'transfer requests';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return self::userHasAnyAssetRole();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return AssetTransferRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetTransferRequests::route('/'),
        ];
    }

    /**
     * Manager-only — never Admin, even the one who created it (spec
     * section 4's matrix: Admin is explicitly ❌ here). Self-approval by
     * a user holding both roles is allowed and simply flagged (spec's
     * confirmed "Self-approval" design), not blocked.
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (AssetTransferRequest $record): bool => self::userIsAssetManager()
                && $record->status === AssetTransferStatus::Pending)
            ->action(function (AssetTransferRequest $record): void {
                AssetLock::once("asset-transfer:{$record->asset_id}", function () use ($record) {
                    $record->refresh();
                    $record->loadMissing(['asset', 'toRoom', 'requestedBy']);

                    if ($record->status !== AssetTransferStatus::Pending) {
                        return;
                    }

                    // Stale check — the asset may have moved by another
                    // path since this request was made (implementation
                    // plan section 3.5).
                    if ($record->asset->room_id !== $record->from_room_id) {
                        Notification::make()
                            ->title('This asset has already moved since the request was made.')
                            ->body("Current location: {$record->asset->room->path()}. Please re-request the transfer.")
                            ->danger()
                            ->send();

                        return;
                    }

                    DB::transaction(function () use ($record) {
                        $record->asset->update(['room_id' => $record->to_room_id]);

                        $record->update([
                            'status' => AssetTransferStatus::Approved,
                            'decided_by' => auth()->id(),
                            'decided_at' => now(),
                        ]);

                        AssetHistory::recordFieldChange($record->asset_id, 'Location', $record->fromRoom->path(), $record->toRoom->path(), 'location_changed');
                        AssetHistory::record($record->asset_id, 'transfer_approved', null, 'transfer_request', $record->id);
                    });

                    app(AssetNotifier::class)->transferDecided($record->fresh(['asset', 'requestedBy']));

                    Notification::make()->title('Transfer approved.')->success()->send();
                });
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (AssetTransferRequest $record): bool => self::userIsAssetManager()
                && $record->status === AssetTransferStatus::Pending)
            ->schema([
                Textarea::make('decision_note')->label('Rejection reason')->required()->rows(2),
            ])
            ->action(function (AssetTransferRequest $record, array $data): void {
                AssetLock::once("asset-transfer:{$record->asset_id}", function () use ($record, $data) {
                    $record->refresh();

                    if ($record->status !== AssetTransferStatus::Pending) {
                        return;
                    }

                    $record->update([
                        'status' => AssetTransferStatus::Rejected,
                        'decided_by' => auth()->id(),
                        'decided_at' => now(),
                        'decision_note' => $data['decision_note'],
                    ]);

                    AssetHistory::record($record->asset_id, 'transfer_rejected', $data['decision_note'], 'transfer_request', $record->id);

                    app(AssetNotifier::class)->transferDecided($record->fresh(['asset', 'requestedBy']));

                    Notification::make()->title('Transfer rejected.')->warning()->send();
                });
            });
    }

    /**
     * The requester may cancel their own pending request; a Manager may
     * also cancel someone else's, with an optional note (spec section
     * 8.12).
     */
    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('gray')
            ->requiresConfirmation()
            ->schema([
                Textarea::make('decision_note')->label('Note (optional)')->rows(2),
            ])
            ->visible(fn (AssetTransferRequest $record): bool => $record->status === AssetTransferStatus::Pending
                && ($record->requested_by === auth()->id() || self::userIsAssetManager()))
            ->action(function (AssetTransferRequest $record, array $data): void {
                AssetLock::once("asset-transfer:{$record->asset_id}", function () use ($record, $data) {
                    $record->refresh();

                    if ($record->status !== AssetTransferStatus::Pending) {
                        return;
                    }

                    if ($record->requested_by !== auth()->id() && ! self::userIsAssetManager()) {
                        Notification::make()->title('Only the requester or a Manager can cancel this request.')->danger()->send();

                        return;
                    }

                    $record->update([
                        'status' => AssetTransferStatus::Cancelled,
                        'decided_by' => auth()->id(),
                        'decided_at' => now(),
                        'decision_note' => $data['decision_note'] ?? null,
                    ]);

                    AssetHistory::record($record->asset_id, 'transfer_cancelled', $data['decision_note'] ?? null, 'transfer_request', $record->id);

                    Notification::make()->title('Transfer request cancelled.')->warning()->send();
                });
            });
    }
}
