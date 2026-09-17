<?php

namespace App\Filament\Assets\Resources\EditRequests;

use App\Enums\AssetEditRequestStatus;
use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\EditRequests\Pages\ListAssetEditRequests;
use App\Filament\Assets\Resources\EditRequests\Tables\AssetEditRequestsTable;
use App\Models\Asset;
use App\Models\AssetEditRequest;
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
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Read/decide-only, same shape as AssetTransferRequestResource — rows
 * are created exclusively via EditAsset::submitEditRequest() when a
 * Posted asset's form is submitted, never through a form here.
 */
class AssetEditRequestResource extends Resource
{
    use HasAssetRoleAccess;

    protected static ?string $model = AssetEditRequest::class;

    protected static ?string $slug = 'asset-edit-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Edit Requests';

    protected static ?string $modelLabel = 'edit request';

    protected static ?string $pluralModelLabel = 'edit requests';

    protected static ?int $navigationSort = 2;

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
        return AssetEditRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetEditRequests::route('/'),
        ];
    }

    /**
     * Manager-only, never Admin — matches every other approval gate in
     * this module (spec section 4's matrix). Self-approval is blocked
     * (approver must differ from the requester) — unlike Transfers/
     * Maintenance, which allow and merely flag it, since an edit
     * request lets the same person both propose and accept arbitrary
     * field changes on a Posted asset, a stronger conflict-of-interest
     * risk. ->visible() hides the button from the requester even if
     * they also hold Manager, and the ->action() closure re-checks
     * server-side in case another tab/session got there first (same
     * shape as AdjustmentResource::postAction() in Inventory). Applies
     * proposed_changes verbatim via Asset::update(), then writes the
     * same per-field history rows a direct Draft-state edit would have.
     * 'photo' isn't a real column — it's pulled out and applied
     * separately via Asset::replacePhoto(), same as a Draft's direct
     * edit does.
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->requiresConfirmation()
            ->modalContent(fn (AssetEditRequest $record): ?View => filled($record->proposed_changes['photo'] ?? null)
                ? view('filament.assets.partials.photo-change-preview', [
                    'currentUrl' => $record->asset->photo_attachment_id
                        ? route('assets.attachments.show', $record->asset->photo_attachment_id)
                        : null,
                    'proposedDataUri' => self::photoDataUri($record->proposed_changes['photo']),
                ])
                : null)
            ->visible(fn (AssetEditRequest $record): bool => self::userIsAssetManager()
                && $record->status === AssetEditRequestStatus::Pending
                && $record->requested_by !== auth()->id())
            ->action(function (AssetEditRequest $record): void {
                AssetLock::once("asset-edit-request:{$record->id}", function () use ($record) {
                    $record->refresh();
                    $record->loadMissing(['asset', 'requestedBy']);

                    if ($record->status !== AssetEditRequestStatus::Pending) {
                        return;
                    }

                    if ($record->requested_by === auth()->id()) {
                        Notification::make()
                            ->title('A different person must approve this request than the one who requested it.')
                            ->danger()
                            ->send();

                        return;
                    }

                    /** @var Asset $asset */
                    $asset = $record->asset;
                    $before = $asset->only(array_keys(Asset::EDITABLE_FIELD_LABELS));

                    $changes = $record->proposed_changes;
                    $newPhotoPath = $changes['photo'] ?? null;
                    unset($changes['photo']);

                    DB::transaction(function () use ($record, $asset, $before, $changes, $newPhotoPath): void {
                        $asset->update($changes);
                        $asset->recordFieldChanges($before);

                        if (filled($newPhotoPath)) {
                            $asset->replacePhoto($newPhotoPath);
                        }

                        $record->update([
                            'status' => AssetEditRequestStatus::Approved,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);

                        AssetHistory::record($asset->id, 'edit_approved', null, 'asset_edit_request', $record->id);
                    });

                    app(AssetNotifier::class)->editDecided($record->fresh(['asset', 'requestedBy']));

                    Notification::make()->title('Edit approved.')->success()->send();
                });
            });
    }

    private static function photoDataUri(string $path): ?string
    {
        $bytes = Storage::disk('local')->get($path);

        if (! $bytes) {
            return null;
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'image/jpeg';

        return "data:{$mime};base64,".base64_encode($bytes);
    }

    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (AssetEditRequest $record): bool => self::userIsAssetManager()
                && $record->status === AssetEditRequestStatus::Pending)
            ->schema([
                Textarea::make('review_note')->label('Rejection reason')->required()->rows(2),
            ])
            ->action(function (AssetEditRequest $record, array $data): void {
                AssetLock::once("asset-edit-request:{$record->id}", function () use ($record, $data) {
                    $record->refresh();
                    $record->loadMissing(['asset', 'requestedBy']);

                    if ($record->status !== AssetEditRequestStatus::Pending) {
                        return;
                    }

                    $record->update([
                        'status' => AssetEditRequestStatus::Rejected,
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                        'review_note' => $data['review_note'],
                    ]);

                    AssetHistory::record($record->asset_id, 'edit_rejected', $data['review_note'], 'asset_edit_request', $record->id);

                    app(AssetNotifier::class)->editDecided($record->fresh(['asset', 'requestedBy']));

                    Notification::make()->title('Edit rejected.')->warning()->send();
                });
            });
    }
}
