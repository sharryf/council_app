<?php

namespace App\Filament\Assets\Resources\Categories\Pages;

use App\Filament\Assets\Resources\Categories\AssetCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetCategory extends CreateRecord
{
    protected static string $resource = AssetCategoryResource::class;
}
