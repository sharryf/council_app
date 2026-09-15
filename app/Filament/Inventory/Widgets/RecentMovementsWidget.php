<?php

namespace App\Filament\Inventory\Widgets;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\StockMovements\Tables\StockMovementColumns;
use App\Models\InventoryStockMovement;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Spec 10.8's "recent receipts and recent issues, 5 each" — merged
 * into one last-10-rows ledger view (the underlying data is the same
 * unified movements table either way) reusing StockMovementColumns
 * exactly as MovementsRelationManager already does, rather than
 * redefining the same columns a third time.
 */
class RecentMovementsWidget extends TableWidget
{
    use HasInventoryRoleAccess;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Stock Activity')
            ->query(InventoryStockMovement::query()->with(['item', 'location', 'performer'])->latest('movement_date'))
            ->columns(StockMovementColumns::make())
            ->paginated([10, 25])
            ->defaultPaginationPageOption(10);
    }
}
