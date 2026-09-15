<?php

namespace App\Filament\Inventory\Resources\Returns\Pages;

use App\Filament\Inventory\Resources\Returns\ReturnResource;
use Filament\Resources\Pages\ListRecords;

class ListReturns extends ListRecords
{
    protected static string $resource = ReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReturnResource::createAction(),
        ];
    }
}
