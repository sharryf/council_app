<?php

namespace App\Filament\Inventory\Resources\Items\Pages;

use App\Filament\Inventory\Resources\Items\ItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add New Item')
                ->visible(fn (): bool => ItemResource::canCreate()),
        ];
    }
}
