<?php

namespace App\Filament\Assets\Resources\FundCodes\Pages;

use App\Filament\Assets\Resources\FundCodes\AssetFundCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssetFundCode extends EditRecord
{
    protected static string $resource = AssetFundCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => AssetFundCodeResource::canDelete($this->record)),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}
