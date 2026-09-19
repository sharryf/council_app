<?php

namespace App\Filament\Assets\Widgets;

use App\Enums\AssetDeleteRequestStatus;
use App\Enums\AssetEditRequestStatus;
use App\Enums\AssetMaintenanceApprovalStatus;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Filament\Assets\Resources\DeleteRequests\AssetDeleteRequestResource;
use App\Filament\Assets\Resources\EditRequests\AssetEditRequestResource;
use App\Filament\Assets\Resources\Maintenance\AssetMaintenanceRecordResource;
use App\Filament\Assets\Resources\Transfers\AssetTransferRequestResource;
use App\Models\Asset;
use App\Models\AssetDeleteRequest;
use App\Models\AssetEditRequest;
use App\Models\AssetMaintenanceRecord;
use App\Models\AssetTransferRequest;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

/**
 * Mirrors Inventory's DashboardOverviewWidget (see its own doc comment)
 * — number cards on the left for every pending queue, a status-count
 * table on the right, replacing the four separate Pending*Widget
 * tables and AssetStatsWidget's row of Stat tiles that used to live on
 * this page. Those widgets are untouched and still fully tested —
 * they're just no longer registered in Dashboard::getWidgets().
 */
class AssetDashboardOverviewWidget extends Widget
{
    use HasAssetRoleAccess;

    protected string $view = 'filament.assets.widgets.dashboard-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return self::userHasAnyAssetRole();
    }

    /**
     * @return array<int, array{label: string, count: int, icon: string, url: string}>
     */
    public function getCards(): array
    {
        $counts = Cache::remember(
            'assets.dashboard.overview.pending.'.auth()->id(),
            60,
            fn (): array => [
                'transfers' => AssetTransferRequest::query()->where('status', AssetTransferStatus::Pending)->count(),
                'maintenance' => AssetMaintenanceRecord::query()->where('approval_status', AssetMaintenanceApprovalStatus::Pending)->count(),
                'editRequests' => AssetEditRequest::query()->where('status', AssetEditRequestStatus::Pending)->count(),
                'deleteRequests' => AssetDeleteRequest::query()->where('status', AssetDeleteRequestStatus::Pending)->count(),
            ],
        );

        return [
            [
                'label' => 'Pending Transfers',
                'count' => $counts['transfers'],
                'icon' => 'heroicon-o-arrows-right-left',
                'url' => AssetTransferRequestResource::getUrl('index', ['tab' => 'pending']),
            ],
            [
                'label' => 'Pending Maintenance',
                'count' => $counts['maintenance'],
                'icon' => 'heroicon-o-wrench-screwdriver',
                'url' => AssetMaintenanceRecordResource::getUrl('index', ['tab' => 'pending']),
            ],
            [
                'label' => 'Pending Edit Requests',
                'count' => $counts['editRequests'],
                'icon' => 'heroicon-o-pencil-square',
                'url' => AssetEditRequestResource::getUrl('index', ['tab' => 'pending']),
            ],
            [
                'label' => 'Pending Delete Requests',
                'count' => $counts['deleteRequests'],
                'icon' => 'heroicon-o-trash',
                'url' => AssetDeleteRequestResource::getUrl('index', ['tab' => 'pending']),
            ],
        ];
    }

    /**
     * Same query AssetStatsWidget uses — kept there too (see its own
     * doc comment) since it's still a valid standalone Stat tile
     * widget, just no longer the one registered on the dashboard.
     *
     * @return array<int, array{label: string, count: int, color: string, url: string}>
     */
    public function getStatusRows(): array
    {
        $counts = Cache::remember(
            'assets.dashboard.overview.status-counts',
            60,
            fn (): array => Asset::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->all(),
        );

        $total = array_sum($counts);

        $rows = [
            ['label' => 'Total Assets', 'count' => $total, 'color' => 'gray', 'url' => AssetResource::getUrl('index')],
        ];

        foreach (AssetStatus::cases() as $status) {
            $rows[] = [
                'label' => $status->getLabel(),
                'count' => $counts[$status->value] ?? 0,
                'color' => $status->getColor(),
                'url' => AssetResource::getUrl('index', ['status' => $status->value]),
            ];
        }

        return $rows;
    }
}
