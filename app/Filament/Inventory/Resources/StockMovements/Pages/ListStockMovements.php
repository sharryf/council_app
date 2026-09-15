<?php

namespace App\Filament\Inventory\Resources\StockMovements\Pages;

use App\Filament\Inventory\Resources\StockMovements\StockMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;
}
