<?php

namespace App\Filament\Inventory\Resources\Items\Pages;

use App\Filament\Inventory\Resources\Items\ItemResource;
use App\Models\InventoryAuditLog;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditItem extends EditRecord
{
    protected static string $resource = ItemResource::class;

    private bool $wasActive = true;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ItemResource::canDelete($this->record)),
        ];
    }

    /**
     * BR-03: an item with on_hand != 0 cannot be deactivated — adjust
     * to zero first. Always satisfiable today since Phase 3 hasn't
     * written any stock yet, but correct once it has.
     */
    protected function beforeSave(): void
    {
        $this->wasActive = (bool) $this->record->is_active;

        $deactivating = $this->record->is_active && ! $this->data['is_active'];

        if ($deactivating && (float) $this->record->stock()->sum('on_hand') !== 0.0) {
            Notification::make()
                ->title('Cannot deactivate — this item still has stock on hand.')
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function afterSave(): void
    {
        $isNowActive = (bool) $this->record->is_active;

        InventoryAuditLog::write(
            'ITEM',
            $this->record->id,
            $this->wasActive && ! $isNowActive ? 'DEACTIVATE' : 'UPDATE',
            ['is_active' => $this->wasActive],
            ['is_active' => $isNowActive],
        );
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }
}
