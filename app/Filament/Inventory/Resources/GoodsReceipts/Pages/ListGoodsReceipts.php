<?php

namespace App\Filament\Inventory\Resources\GoodsReceipts\Pages;

use App\Filament\Inventory\Resources\GoodsReceipts\GoodsReceiptResource;
use Filament\Resources\Pages\ListRecords;

class ListGoodsReceipts extends ListRecords
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GoodsReceiptResource::createAction(),
        ];
    }
}
