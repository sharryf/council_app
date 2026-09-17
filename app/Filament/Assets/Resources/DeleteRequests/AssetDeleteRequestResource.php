<?php

namespace App\Filament\Assets\Resources\DeleteRequests;

use App\Enums\AssetDeleteRequestStatus;
use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\DeleteRequests\Pages\ListAssetDeleteRequests;
use App\Filament\Assets\Resources\DeleteRequests\Tables\AssetDeleteRequestsTable;
use App\Models\AssetDeleteRequest;
use App\Models\AssetHistory;
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

/**
 * Read/decide-only — rows are created exclusively via
 * AssetResource::requestDeleteAction() from the asset's own View page,
 * never through a generic create form here (same shape as
 * AssetTransferRequestResource/AssetEditRequestResource).
 */
class AssetDeleteRequestResource extends Resource
{
    use HasAssetRoleAccess;

    protected static ?string $model = AssetDeleteRequest::class;

    protected static ?string $slug = 'asset-delete-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static ?string $navigationLabel = 'Delete Requests';

    protected static ?string $modelLabel = 'delete request';

    protected static ?string $pluralModelLabel = 'delete requests';

    protected static ?int $navigationSort = 3;

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
        return AssetDeleteRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetDeleteRequests::route('/'),
        ];
    }

    /**
     * Manager-only, and the approver must differ from the Manager who
     * requested it (segregation of duties — unlike Transfers, which
     * allow and merely flag self-approval, deletion is destructive and
     * irreversible enough to warrant a hard block). ->visible() hides
     * the button from the requester even if they're the only other
     * Manager around, and the ->action() closure re-checks server-side
     * in case another tab/session got there first.
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheck)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This permanently deletes the asset. This cannot be undone.')
            ->visible(fn (AssetDeleteRequest $record): bool => self::userIsAssetManager()
                && $record->status === AssetDeleteRequestStatus::Pending
                && $record->requested_by !== auth()->id())
            ->action(function (AssetDeleteRequest $record): void {
                AssetLock::once("asset-delete-request:{$record->id}", function () use ($record) {
                    $record->refresh();
                    $record->loadMissing(['asset', 'requestedBy']);

                    if ($record->status !== AssetDeleteRequestStatus::Pending) {
                        return;
                    }

                    if ($record->requested_by === auth()->id()) {
                        Notification::make()
                            ->title('A different person must approve this request than the one who requested it.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $asset = $record->asset;

                    $record->update([
                        'status' => AssetDeleteRequestStatus::Approved,
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                    ]);

                    // Logged before delete() — AssetHistory rows key off
                    // asset_id, not a live relation, so they still read
                    // fine once the asset itself is gone.
                    AssetHistory::record($asset->id, 'delete_approved', null, 'asset_delete_request', $record->id);

                    $asset->delete();

                    // Not ->fresh() — the belongsTo would re-query and
                    // come back null now that the asset is trashed, so
                    // the already-loaded (still soft-deleted-but-intact)
                    // $asset instance is reattached instead.
                    $record->setRelation('asset', $asset);
                    app(AssetNotifier::class)->deleteDecided($record);

                    Notification::make()->title('Asset deleted.')->success()->send();
                });
            });
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (AssetDeleteRequest $record): bool => self::userIsAssetManager()
                && $record->status === AssetDeleteRequestStatus::Pending)
            ->schema([
                Textarea::make('review_note')->label('Rejection reason')->required()->rows(2),
            ])
            ->action(function (AssetDeleteRequest $record, array $data): void {
                AssetLock::once("asset-delete-request:{$record->id}", function () use ($record, $data) {
                    $record->refresh();
                    $record->loadMissing(['asset', 'requestedBy']);

                    if ($record->status !== AssetDeleteRequestStatus::Pending) {
                        return;
                    }

                    $record->update([
                        'status' => AssetDeleteRequestStatus::Rejected,
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'review_note' => $data['review_note'],
                    ]);

                    AssetHistory::record($record->asset_id, 'delete_rejected', $data['review_note'], 'asset_delete_request', $record->id);

                    app(AssetNotifier::class)->deleteDecided($record->fresh(['asset', 'requestedBy']));

                    Notification::make()->title('Delete request rejected.')->warning()->send();
                });
            });
    }
}
