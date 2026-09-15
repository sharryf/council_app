<?php

namespace App\Filament\Inventory\Widgets;

use App\Enums\InventoryMovementType;
use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Models\InventoryStockMovement;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

/**
 * Spec 10.8's "movement chart: qty in vs out, last 30 days" — the
 * "top 10 items by movement count" chart the spec also mentions is a
 * genuinely separate chart, not built this phase (see Phase 6 plan's
 * scope-trim note).
 */
class MovementChartWidget extends ChartWidget
{
    use HasInventoryRoleAccess;

    protected ?string $heading = 'Stock Movement — Last 30 Days';

    protected int|string|array $columnSpan = 'full';

    // See InventoryStatsOverview's own comment — lazy-loading's
    // x-intersect follow-up doesn't reliably fire in this environment.
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        return Cache::remember('inventory.dashboard.movement-chart', 60, function (): array {
            $days = collect(range(29, 0))->map(fn (int $i): string => now()->subDays($i)->toDateString());

            $rows = InventoryStockMovement::query()
                ->whereIn('movement_type', [InventoryMovementType::Receipt->value, InventoryMovementType::Issue->value])
                ->where('movement_date', '>=', now()->subDays(29)->startOfDay())
                ->selectRaw('DATE(movement_date) as day, movement_type, SUM(quantity) as total')
                ->groupBy('day', 'movement_type')
                ->get();

            $totalFor = fn (string $day, string $type): float => (float) ($rows
                ->first(fn ($row): bool => $row->day === $day && $row->movement_type === $type)
                ?->total ?? 0);

            return [
                'datasets' => [
                    [
                        'label' => 'Received',
                        'data' => $days->map(fn (string $day): float => $totalFor($day, InventoryMovementType::Receipt->value))->all(),
                        'backgroundColor' => '#22c55e',
                    ],
                    [
                        'label' => 'Issued',
                        'data' => $days->map(fn (string $day): float => $totalFor($day, InventoryMovementType::Issue->value))->all(),
                        'backgroundColor' => '#ef4444',
                    ],
                ],
                'labels' => $days->map(fn (string $day): string => Carbon::parse($day)->format('d/m'))->all(),
            ];
        });
    }
}
