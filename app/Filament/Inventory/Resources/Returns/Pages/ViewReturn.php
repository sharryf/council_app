<?php

namespace App\Filament\Inventory\Resources\Returns\Pages;

use App\Filament\Inventory\Resources\Returns\ReturnResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReturn extends ViewRecord
{
    protected static string $resource = ReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => ReturnResource::canEdit($this->record)),
            ReturnResource::postAction(),
            ReturnResource::reverseAction(),
        ];
    }
}
