<?php

namespace App\Filament\Assets\Resources\DeleteRequests\Pages;

use App\Enums\AssetDeleteRequestStatus;
use App\Filament\Assets\Resources\DeleteRequests\AssetDeleteRequestResource;
use App\Models\AssetDeleteRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListAssetDeleteRequests extends ListRecords
{
    protected static string $resource = AssetDeleteRequestResource::class;

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];

        if (AssetDeleteRequestResource::userIsAssetManager()) {
            $tabs = ['pending' => Tab::make('Pending')
                ->query(fn ($query) => $query->where('status', AssetDeleteRequestStatus::Pending))
                ->badge(fn () => AssetDeleteRequest::query()->where('status', AssetDeleteRequestStatus::Pending)->count())
                ->badgeColor('warning'),
                ...$tabs];
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return AssetDeleteRequestResource::userIsAssetManager() ? 'pending' : 'all';
    }
}
