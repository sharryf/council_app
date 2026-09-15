<?php

namespace App\Filament\Assets\Resources\Maintenance;

use App\Enums\AssetMaintenanceApprovalStatus;
use App\Enums\AssetStatus;
use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\Maintenance\Pages\ListAssetMaintenanceRecords;
use App\Filament\Assets\Resources\Maintenance\Tables\AssetMaintenanceRecordsTable;
use App\Models\AssetHistory;
use App\Models\AssetMaintenanceRecord;
use App\Services\Assets\AssetLock;
use App\Services\Assets\AssetNotifier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
     * approving never touches assets.status (implementation plan
     * section 3.6/6.6).
     */
    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('This validates the record only — it does not change the asset\'s status.')
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
                    ]);

                    AssetHistory::record($record->asset_id, 'maintenance_approved', null, 'maintenance_record', $record->id);

                    app(AssetNotifier::class)->maintenanceDecided($record->fresh(['asset', 'recordedBy']));

                    Notification::make()->title('Maintenance record approved.')->success()->send();
                });
            });
    }

    /**
     * Rejecting does NOT restore the asset's status — it's already
     * Under Repair and stays that way until an Admin explicitly closes
     * it out (implementation plan section 3.6/8.14).
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
     * Admin-only. Allowed regardless of approval_status (spec section
     * 2.2/3.6 — approval validates the record, closing settles the
     * asset's status; the two are independent). closing_status defaults
     * to previous_status but is Admin-overridable (e.g. Retired if
     * beyond repair) — never auto-restored silently.
     */
    public static function closeAction(): Action
    {
        return Action::make('close')
            ->label('Close Maintenance')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('primary')
            ->visible(fn (AssetMaintenanceRecord $record): bool => self::userIsAssetAdmin() && $record->isOpen())
            ->schema(fn (AssetMaintenanceRecord $record) => [
                Select::make('closing_status')
                    ->label('Closing status')
                    ->options(collect(AssetStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()]))
                    ->default($record->previous_status->value)
                    ->required(),
            ])
            ->action(function (AssetMaintenanceRecord $record, array $data): void {
                AssetLock::once("asset-maintenance-record:{$record->id}", function () use ($record, $data) {
                    $record->refresh();

                    if (! $record->isOpen()) {
                        return;
                    }

                    DB::transaction(function () use ($record, $data) {
                        $record->asset->update(['status' => $data['closing_status']]);

                        $record->update([
                            'closing_status' => $data['closing_status'],
                            'closed_at' => now(),
                            'closed_by' => auth()->id(),
                        ]);

                        AssetHistory::recordFieldChange($record->asset_id, 'Status', 'Under Repair', AssetStatus::from($data['closing_status'])->getLabel(), 'status_changed');
                        AssetHistory::record($record->asset_id, 'maintenance_closed', null, 'maintenance_record', $record->id);
                    });

                    Notification::make()->title('Maintenance closed.')->success()->send();
                });
            });
    }
}
