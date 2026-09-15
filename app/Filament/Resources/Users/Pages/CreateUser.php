<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Concerns\InteractsWithModuleAccess;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use InteractsWithModuleAccess;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractModuleLevelFields($data);
    }

    protected function afterCreate(): void
    {
        $this->persistModuleLevels($this->record);
    }
}
