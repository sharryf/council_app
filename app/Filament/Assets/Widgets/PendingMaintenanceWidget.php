<?php

namespace App\Filament\Assets\Widgets;

use App\Enums\AssetMaintenanceApprovalStatus;
use App\Filament\Assets\Resources\Maintenance\AssetMaintenanceRecordResource;
use App\Models\AssetMaintenanceRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Spec section 6.1's "Pending Approvals" — the second queue. Same
 * reuse-the-resource's-own-actions approach as PendingTransfersWidget.
 */
class PendingMaintenanceWidget extends TableWidget
{
    protected static ?string $heading = 'Pending Maintenance';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return AssetMaintenanceRecordResource::userHasAnyAssetRole();
    }

    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->query(AssetMaintenanceRecord::query()->where('approval_status', AssetMaintenanceApprovalStatus::Pending)->with(['asset', 'recordedBy']))
            ->columns([
                TextColumn::make('asset.name')->label('Asset')->wrap(),
                TextColumn::make('recordedBy.name')->label('Logged by'),
                TextColumn::make('maintenance_date')->label('Date')->date(),
            ])
            ->recordActions([
                AssetMaintenanceRecordResource::approveAction(),
                AssetMaintenanceRecordResource::rejectAction(),
            ])
            ->emptyStateHeading('No pending maintenance')
            ->paginated(false);
    }
}
