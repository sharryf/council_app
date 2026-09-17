<?php

namespace App\Filament\Assets\Pages;

use App\Filament\Assets\Widgets\AssetStatsWidget;
use App\Filament\Assets\Widgets\OpenAuditWidget;
use App\Filament\Assets\Widgets\PendingDeleteRequestsWidget;
use App\Filament\Assets\Widgets\PendingEditRequestsWidget;
use App\Filament\Assets\Widgets\PendingMaintenanceWidget;
use App\Filament\Assets\Widgets\PendingTransfersWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Spec section 6.1: pending approvals (the most prominent element) at
 * the top, stats + the open-audit banner below. Each widget gates its
 * own visibility per AssetRole, and reuses the same tested
 * approve/reject actions the Transfers/Maintenance list pages already
 * use — see the widgets' own doc comments.
 */
class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            PendingTransfersWidget::class,
            PendingMaintenanceWidget::class,
            PendingEditRequestsWidget::class,
            PendingDeleteRequestsWidget::class,
            OpenAuditWidget::class,
            AssetStatsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
