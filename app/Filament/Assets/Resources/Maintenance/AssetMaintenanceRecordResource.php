<?php

namespace App\Filament\Assets\Resources\Maintenance;

use App\Enums\AssetMaintenanceApprovalStatus;
use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\Maintenance\Pages\ListAssetMaintenanceRecords;
use App\Filament\Assets\Resources\Maintenance\Tables\AssetMaintenanceRecordsTable;
use App\Models\AssetHistory;
use App\Models\AssetMaintenanceRecord;
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
 * AssetResource::logMaintenanceAction() from the asset's own View page.
 */
class AssetMaintenanceRecordResource extends Resource
{
    use HasAssetRoleAccess;

    protected static ?string $model = AssetMaintenanceRecord::class;

    protected static ?string $slug = 'asset-maintenance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Maintenance';

    protected static ?string $modelLabel = 'maintenance record';

    protected static ?string $pluralModelLabel = 'maintenance records';

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
        return AssetMaintenanceRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetMaintenanceRecords::route('/'),
        ];
    }

    /**
     * Manager-only, validates the RECORD, not the asset's status —
     * approving never touches assets.status, and now closes the record
     * automatically in the same step (it no longer needs a separate
     * Admin close-out once it's approved).
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('This approves and closes the record — it does not change the asset\'s status.')
            ->visible(fn (AssetMaintenanceRecord $record): bool => self::userIsAssetManager()
                && $record->approval_status === AssetMaintenanceApprovalStatus::Pending)
            ->action(function (AssetMaintenanceRecord $record): void {
                AssetLock::once("asset-maintenance-record:{$record->id}", function () use ($record) {
                    $record->refresh();

                    if ($record->approval_status !== AssetMaintenanceApprovalStatus::Pending) {
                        return;
                    }

                    $record->update([
                        'approval_status' => AssetMaintenanceApprovalStatus::Approved,
                        'decided_by' => auth()->id(),
                        'decided_at' => now(),
                        'closed_at' => now(),
                        'closed_by' => auth()->id(),
                    ]);

                    AssetHistory::record($record->asset_id, 'maintenance_approved', null, 'maintenance_record', $record->id);
                    AssetHistory::record($record->asset_id, 'maintenance_closed', 'Closed automatically on approval.', 'maintenance_record', $record->id);

                    app(AssetNotifier::class)->maintenanceDecided($record->fresh(['asset', 'recordedBy']));

                    Notification::make()->title('Maintenance record approved and closed.')->success()->send();
                });
            });
    }

    /**
     * Rejecting does NOT close the record out — it's left open for an
     * Admin to close manually (via closeAction()) once whatever it
     * flagged has been dealt with outside the system. Never touches the
     * asset's status either way.
     */
    public static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->visible(fn (AssetMaintenanceRecord $record): bool => self::userIsAssetManager()
                && $record->approval_status === AssetMaintenanceApprovalStatus::Pending)
            ->schema([
                Textarea::make('decision_note')->label('Rejection reason')->required()->rows(2),
            ])
            ->action(function (AssetMaintenanceRecord $record, array $data): void {
                AssetLock::once("asset-maintenance-record:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if ($record->approval_status !== AssetMaintenanceApprovalStatus::Pending) {
                        return;
                    }

                    $record->update([
                        'approval_status' => AssetMaintenanceApprovalStatus::Rejected,
                        'decided_by' => auth()->id(),
                        'decided_at' => now(),
                        'decision_note' => $data['decision_note'],
                    ]);

                    AssetHistory::record($record->asset_id, 'maintenance_rejected', $data['decision_note'], 'maintenance_record', $record->id);

                    app(AssetNotifier::class)->maintenanceDecided($record->fresh(['asset', 'recordedBy']));

                    Notification::make()->title('Maintenance record rejected — it is still open and must be closed out.')->warning()->send();
                });
            });
    }

    /**
     * Admin-only. Approving already closes a record automatically —
     * this exists for the one case that doesn't: a rejected record,
     * left open on purpose for an Admin to close once it's been dealt
     * with. Never touches the asset's status.
     */
    public static function closeAction(): Action
    {
        return Action::make('close')
            ->label('Close Maintenance')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('This marks the record closed. It does not change the asset\'s status.')
            ->visible(fn (AssetMaintenanceRecord $record): bool => self::userIsAssetAdmin() && $record->isOpen())
            ->action(function (AssetMaintenanceRecord $record): void {
                AssetLock::once("asset-maintenance-record:{$record->id}", function () use ($record) {
                    $record->refresh();

                    if (! $record->isOpen()) {
                        return;
                    }

                    $record->update([
                        'closed_at' => now(),
                        'closed_by' => auth()->id(),
                    ]);

                    AssetHistory::record($record->asset_id, 'maintenance_closed', null, 'maintenance_record', $record->id);

                    Notification::make()->title('Maintenance closed.')->success()->send();
                });
            });
    }
}
