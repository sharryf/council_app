<?php

namespace App\Filament\Assets\Resources\Buildings\Pages;

use App\Filament\Assets\Resources\Buildings\AssetBuildingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetBuildings extends ListRecords
{
    protected static string $resource = AssetBuildingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => AssetBuildingResource::canCreate()),
        ];
    }
}
