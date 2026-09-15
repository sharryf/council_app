<?php

namespace App\Filament\Inventory\Resources\Items\Pages;

use App\Filament\Inventory\Resources\Items\ItemResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItem extends ViewRecord
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => ItemResource::canEdit($this->record)),
        ];
    }
}
