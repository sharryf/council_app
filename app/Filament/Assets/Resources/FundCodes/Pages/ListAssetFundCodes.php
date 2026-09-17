<?php

namespace App\Filament\Assets\Resources\FundCodes\Pages;

use App\Filament\Assets\Resources\FundCodes\AssetFundCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetFundCodes extends ListRecords
{
    protected static string $resource = AssetFundCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => AssetFundCodeResource::canCreate()),
        ];
    }
}
