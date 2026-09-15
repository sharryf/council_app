<?php

namespace App\Filament\Inventory\Resources\StockMovements;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Inventory\Resources\StockMovements\Tables\StockMovementsTable;
use App\Models\InventoryStockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Spec 10.1's standalone "Movements (full ledger)" nav item — list-only,
 * movements are never independently created/edited/deleted here, only
 * browsed (the only writer is StockMovementService, see Phase 3).
 */
class StockMovementResource extends Resource
{
    use HasInventoryRoleAccess;

    protected static ?string $model = InventoryStockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Movements';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'movement';

    /**
     * Stock Admin or above — a plain User has no reason to see the
     * ledger (they only ever raise/track their own Issue Requests, per
     * the module's own role design).
     */
    public static function canAccess(): bool
    {
        return self::userIsStockAdminOrAbove();
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
        return StockMovementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
