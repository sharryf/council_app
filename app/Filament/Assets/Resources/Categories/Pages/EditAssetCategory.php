<?php

namespace App\Filament\Assets\Resources\Categories\Pages;

use App\Filament\Assets\Resources\Categories\AssetCategoryResource;
use App\Models\AssetCategory;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAssetCategory extends EditRecord
{
    protected static string $resource = AssetCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => AssetCategoryResource::canDelete($this->record)),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Blocks deactivating a category any non-deleted asset references —
     * directly, or via one of its sub-categories (implementation plan
     * section 3.1). Naming the blocking count so the admin knows why.
     */
    protected function beforeSave(): void
    {
        /** @var AssetCategory $record */
        $record = $this->record;

        $isDeactivating = $record->is_active && ! (bool) $this->data['is_active'];

        if ($isDeactivating && AssetCategoryResource::isReferenced($record)) {
            $count = $record->parent_id === null
                ? AssetCategory::query()->whereKey([$record->id, ...$record->children()->pluck('id')])->withCount('assets')->get()->sum('assets_count')
                : $record->assets()->count();

            Notification::make()
                ->title('Cannot deactivate this category')
                ->body("{$count} asset(s) currently use it or one of its sub-categories.")
                ->danger()
                ->send();

            $this->halt();
        }
    }

    /**
     * Deactivating a parent cascades to its children (spec section
     * 3.1) — the helper text on the is_active toggle already tells the
     * admin this happens, so there's no separate confirmation step.
     */
    protected function afterSave(): void
    {
        /** @var AssetCategory $record */
        $record = $this->record;

        if (! $record->is_active && $record->parent_id === null) {
            $record->children()->where('is_active', true)->update(['is_active' => false]);
        }
    }
}
