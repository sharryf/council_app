<?php

namespace App\Filament\Inventory\Resources\GoodsReceipts\Pages;

use App\Filament\Inventory\Resources\GoodsReceipts\GoodsReceiptResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGoodsReceipt extends ViewRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => GoodsReceiptResource::canEdit($this->record)),
            GoodsReceiptResource::postAction(),
            GoodsReceiptResource::requestReversalAction(),
            GoodsReceiptResource::approveReversalAction(),
            GoodsReceiptResource::rejectReversalAction(),
        ];
    }
}
