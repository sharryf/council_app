<?php

namespace App\Filament\Inventory\Resources\UnitsOfMeasure\Pages;

use App\Filament\Inventory\Resources\UnitsOfMeasure\UnitOfMeasureResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUnitsOfMeasure extends ListRecords
{
    protected static string $resource = UnitOfMeasureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
