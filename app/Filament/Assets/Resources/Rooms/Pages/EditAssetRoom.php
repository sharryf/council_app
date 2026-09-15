<?php

namespace App\Filament\Assets\Resources\Rooms\Pages;

use App\Filament\Assets\Resources\Rooms\AssetRoomResource;
use App\Models\AssetRoom;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAssetRoom extends EditRecord
{
    protected static string $resource = AssetRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => AssetRoomResource::canDelete($this->record)),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Cannot deactivate a room with assets in it (implementation plan
     * section 3.2) — the assets must be transferred out first.
     */
    protected function beforeSave(): void
    {
        /** @var AssetRoom $record */
        $record = $this->record;

        $isDeactivating = $record->is_active && ! (bool) $this->data['is_active'];

        if ($isDeactivating && $record->assets()->exists()) {
            $count = $record->assets()->count();

            Notification::make()
                ->title('Cannot deactivate this room')
                ->body("It still has {$count} asset(s) in it. Transfer them out first.")
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
