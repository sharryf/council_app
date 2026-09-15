<?php

namespace App\Filament\Inventory\Resources\Returns\Pages;

use App\Filament\Inventory\Resources\Returns\ReturnResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReturn extends EditRecord
{
    protected static string $resource = ReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ReturnResource::canDelete($this->record)),
            ReturnResource::postAction(),
        ];
    }
}
