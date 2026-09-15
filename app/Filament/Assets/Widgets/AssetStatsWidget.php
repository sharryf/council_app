<?php

namespace App\Filament\Assets\Widgets;

use App\Enums\AssetStatus;
use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Spec section 6.1: total assets + a count per status, each clickable
 * and deep-linking to the filtered asset list.
 */
class AssetStatsWidget extends StatsOverviewWidget
{
    use HasAssetRoleAccess;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return self::userHasAnyAssetRole();
    }

    protected function getStats(): array
    {
        $counts = Asset::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = $counts->sum();

        $stats = [
            Stat::make('Total Assets', (string) $total)
                ->url(AssetResource::getUrl('index')),
        ];

        foreach (AssetStatus::cases() as $status) {
            $count = $counts[$status->value] ?? 0;

            $stats[] = Stat::make($status->getLabel(), (string) $count)
                ->color($status->getColor())
                ->url(AssetResource::getUrl('index', ['status' => $status->value]));
        }

        return $stats;
    }
}
