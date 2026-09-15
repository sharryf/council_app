<?php

namespace App\Filament\Inventory\Resources\ItemCategories\Pages;

use App\Filament\Inventory\Resources\ItemCategories\ItemCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateItemCategory extends CreateRecord
{
    protected static string $resource = ItemCategoryResource::class;
}
