<?php

namespace App\Filament\Assets\Resources\Categories\Pages;

use App\Filament\Assets\Resources\Categories\AssetCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetCategories extends ListRecords
{
    protected static string $resource = AssetCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => AssetCategoryResource::canCreate()),
        ];
    }
}
