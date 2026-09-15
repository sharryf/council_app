<?php

namespace App\Filament\Assets\Resources\Buildings\Pages;

use App\Filament\Assets\Resources\Buildings\AssetBuildingResource;
use App\Models\AssetBuilding;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAssetBuilding extends EditRecord
{
    protected static string $resource = AssetBuildingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => AssetBuildingResource::canDelete($this->record)),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Cannot deactivate a building with active rooms (implementation
     * plan section 3.2) — rooms must be deactivated individually first.
     */
    protected function beforeSave(): void
    {
        /** @var AssetBuilding $record */
        $record = $this->record;

        $isDeactivating = $record->is_active && ! (bool) $this->data['is_active'];

        if ($isDeactivating && $record->rooms()->where('is_active', true)->exists()) {
            $count = $record->rooms()->where('is_active', true)->count();

            Notification::make()
                ->title('Cannot deactivate this building')
                ->body("It still has {$count} active room(s). Deactivate them first.")
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
