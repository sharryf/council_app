<?php

namespace App\Filament\Assets\Pages;

use App\Filament\Assets\Widgets\AssetDashboardOverviewWidget;
use App\Filament\Assets\Widgets\OpenAuditWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Spec section 6.1: pending approvals (the most prominent element) at
 * the top, status counts alongside, the open-audit banner below — same
 * redesigned layout as Inventory's own Dashboard (see its own doc
 * comment): one role-aware overview widget with number cards + a
 * status table, instead of a grid of separate pending-queue tables and
 * a row of Stat tiles. The widgets that used to be registered here
 * (PendingTransfersWidget, PendingMaintenanceWidget,
 * PendingEditRequestsWidget, PendingDeleteRequestsWidget,
 * AssetStatsWidget) still exist and are still tested — they're just no
 * longer registered here; see AssetDashboardOverviewWidget's own doc
 * comment.
 */
class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            AssetDashboardOverviewWidget::class,
            OpenAuditWidget::class,
        ];
    }
}
