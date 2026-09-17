<?php

namespace App\Filament\Assets\Resources\Assets;

use App\Enums\AssetDeleteRequestStatus;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetTransferStatus;
use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\Assets\Pages\CreateAsset;
use App\Filament\Assets\Resources\Assets\Pages\EditAsset;
use App\Filament\Assets\Resources\Assets\Pages\ListAssets;
use App\Filament\Assets\Resources\Assets\Pages\ViewAsset;
use App\Filament\Assets\Resources\Assets\Schemas\AssetForm;
use App\Filament\Assets\Resources\Assets\Schemas\AssetInfolist;
use App\Filament\Assets\Resources\Assets\Tables\AssetsTable;
use App\Models\Asset;
use App\Models\AssetDeleteRequest;
use App\Models\AssetHistory;
use App\Models\AssetMaintenanceRecord;
use App\Models\AssetRoom;
use App\Models\AssetTransferRequest;
use App\Services\Assets\AssetLock;
use App\Services\Assets\AssetNotifier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AssetResource extends Resource
{
    use HasAssetRoleAccess;

    protected static ?string $model = Asset::class;

    protected static ?string $slug = 'assets';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?string $navigationLabel = 'Assets';

    protected static ?int $navigationSort = 0;

    /**
     * A user holding none of the three AssetRoles sees nothing here —
     * see HasAssetRoleAccess's doc comment on why.
     */
    public static function canAccess(): bool
    {
        return self::userHasAnyAssetRole();
    }

    /**
     * Create/Edit/Delete/Status-change/Photo — Admin only (spec section
     * 4's permission matrix; Manager and Viewer are both ❌ on every
     * mutation).
     */
    public static function canCreate(): bool
    {
        return self::userIsAssetAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return self::userIsAssetAdmin();
    }

    /**
     * Draft only — an instant, no-approval Admin delete, matching every
     * other Draft mutation in this module. A Posted asset can no longer
     * be deleted outright at all; it goes through
     * requestDeleteAction()'s Manager-initiated, different-Manager-
     * approved flow instead (deletion is destructive/irreversible
     * enough to warrant the same segregation-of-duties treatment as
     * Inventory's post/approve split, stronger than the plain approval
     * gate Transfers/Edits use).
     */
    public static function canDelete(Model $record): bool
    {
        /** @var Asset $record */
        return self::userIsAssetAdmin() && $record->isDraft() && ! $record->hasActiveAuditItem();
    }

    public static function form(Schema $schema): Schema
    {
        return AssetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssets::route('/'),
            'create' => CreateAsset::route('/create'),
            'view' => ViewAsset::route('/{record}'),
            'edit' => EditAsset::route('/{record}/edit'),
        ];
    }

    /**
     * Admin-only; the one-way Draft→Posted lock. A photo is required
     * before posting — CreateAsset already requires one to create the
     * asset at all, but this re-checks it in case that ever changes
     * (e.g. a future bulk import that skips it).
     */
    public static function postAction(): Action
    {
        return Action::make('post')
            ->label('Post')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('This locks the asset — further edits will need a Manager\'s approval.')
            ->visible(fn (Asset $record): bool => self::userIsAssetAdmin() && $record->isDraft())
            ->action(function (Asset $record): void {
                AssetLock::once("asset-post:{$record->id}", function () use ($record) {
                    $record->refresh();

                    if (! $record->isDraft()) {
                        return;
                    }

                    if ($record->photo_attachment_id === null) {
                        Notification::make()->title('Add a photo before posting.')->danger()->send();

                        return;
                    }

                    $record->update(['lifecycle_status' => AssetLifecycleStatus::Posted]);

                    AssetHistory::record($record->id, 'posted', "Posted as {$record->asset_tag}.");

                    Notification::make()->title('Asset posted.')->success()->send();
                });
            });
    }

    /**
     * Admin-only; blocked for a terminal (disposed) asset, and while a
     * transfer is already pending — one pending request per asset
     * (implementation plan section 3.5/8.9), re-checked server-side
     * inside the action, not just via ->visible().
     */
    public static function requestTransferAction(): Action
    {
        return Action::make('requestTransfer')
            ->label('Change Location')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (Asset $record): bool => self::userIsAssetAdmin()
                && ! $record->status->isTerminal()
                && ! $record->hasPendingTransfer())
            ->schema(fn (Asset $record) => [
                Select::make('to_room_id')
                    ->label('New room')
                    ->options(fn () => AssetRoom::query()
                        ->where('is_active', true)
                        ->whereKeyNot($record->room_id)
                        ->with('building')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (AssetRoom $room): array => [$room->id => $room->path()]))
                    ->required()
                    ->searchable(),
                Textarea::make('reason')->label('Reason')->rows(2),
            ])
            ->action(function (Asset $record, array $data): void {
                AssetLock::once("asset-transfer:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if ($record->status->isTerminal() || $record->hasPendingTransfer()) {
                        Notification::make()->title('This asset can no longer be transferred — refresh and check its current state.')->danger()->send();

                        return;
                    }

                    $request = DB::transaction(function () use ($record, $data): AssetTransferRequest {
                        $request = AssetTransferRequest::create([
                            'asset_id' => $record->id,
                            'from_room_id' => $record->room_id,
                            'to_room_id' => $data['to_room_id'],
                            'reason' => $data['reason'] ?? null,
                            'status' => AssetTransferStatus::Pending,
                            'requested_by' => auth()->id(),
                            'requested_at' => now(),
                        ]);

                        AssetHistory::record($record->id, 'transfer_requested', "Requested move to {$request->toRoom->path()}.", 'transfer_request', $request->id);

                        return $request;
                    });

                    app(AssetNotifier::class)->transferRequested($request->fresh(['asset', 'requestedBy', 'toRoom']));

                    Notification::make()->title('Transfer requested.')->success()->send();
                });
            });
    }

    /**
     * Admin-only; blocked for a terminal asset and while another
     * maintenance record is already open — one open record per asset
     * (implementation plan section 3.6/8.15).
     */
    public static function logMaintenanceAction(): Action
    {
        return Action::make('logMaintenance')
            ->label('Log Maintenance')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('gray')
            ->visible(fn (Asset $record): bool => self::userIsAssetAdmin()
                && ! $record->status->isTerminal()
                && $record->openMaintenanceRecord() === null)
            ->schema([
                Textarea::make('description')->label('Description')->required()->rows(2),
                DatePicker::make('maintenance_date')->label('Date')->required()->default(now())->maxDate(now()),
                TextInput::make('cost')->label('Cost (optional)')->numeric()->prefix('MVR'),
            ])
            ->action(function (Asset $record, array $data): void {
                AssetLock::once("asset-maintenance:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if ($record->status->isTerminal() || $record->openMaintenanceRecord() !== null) {
                        Notification::make()->title('This asset already has an open maintenance record — refresh and check its current state.')->danger()->send();

                        return;
                    }

                    // Maintenance no longer touches the asset's condition
                    // (assets.status) at all — that's only ever changed
                    // by Editing the asset. previous_status is still
                    // captured purely as a record of what the condition
                    // was at the time, for reference.
                    $recordEntry = DB::transaction(function () use ($record, $data): AssetMaintenanceRecord {
                        $recordEntry = AssetMaintenanceRecord::create([
                            'asset_id' => $record->id,
                            'description' => $data['description'],
                            'maintenance_date' => $data['maintenance_date'],
                            'cost' => $data['cost'] ?? null,
                            'previous_status' => $record->status,
                            'approval_status' => 'pending',
                            'recorded_by' => auth()->id(),
                        ]);

                        AssetHistory::record($record->id, 'maintenance_logged', $data['description'], 'maintenance_record', $recordEntry->id);

                        return $recordEntry;
                    });

                    app(AssetNotifier::class)->maintenanceLogged($recordEntry->fresh());

                    Notification::make()->title('Maintenance logged.')->success()->send();
                });
            });
    }

    /**
     * Manager-only to initiate (deliberately not Admin, unlike every
     * other request-flow action above) — approval is a different
     * Manager via AssetDeleteRequestResource::approveAction(). Only
     * reachable for a Posted asset; a Draft one is still deleted
     * directly and instantly through canDelete() above. One pending
     * request per asset, re-checked server-side, same pattern as
     * requestTransferAction().
     */
    public static function requestDeleteAction(): Action
    {
        return Action::make('requestDelete')
            ->label('Request Deletion')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (Asset $record): bool => self::userIsAssetManager()
                && $record->isPosted()
                && ! $record->hasPendingDeleteRequest()
                && ! $record->hasActiveAuditItem())
            ->schema([
                Textarea::make('reason')->label('Reason')->required()->rows(2),
            ])
            ->action(function (Asset $record, array $data): void {
                AssetLock::once("asset-delete-request:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if (! $record->isPosted() || $record->hasPendingDeleteRequest() || $record->hasActiveAuditItem()) {
                        Notification::make()->title('This asset can no longer have a deletion requested — refresh and check its current state.')->danger()->send();

                        return;
                    }

                    $request = DB::transaction(function () use ($record, $data): AssetDeleteRequest {
                        $request = AssetDeleteRequest::create([
                            'asset_id' => $record->id,
                            'reason' => $data['reason'],
                            'status' => AssetDeleteRequestStatus::Pending,
                            'requested_by' => auth()->id(),
                            'requested_at' => now(),
                        ]);

                        AssetHistory::record($record->id, 'delete_requested', $data['reason'], 'asset_delete_request', $request->id);

                        return $request;
                    });

                    app(AssetNotifier::class)->deleteRequested($request->fresh(['asset', 'requestedBy']));

                    Notification::make()->title('Deletion requested.')->success()->send();
                });
            });
    }
}
