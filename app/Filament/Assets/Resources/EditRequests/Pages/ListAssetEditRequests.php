<?php

namespace App\Filament\Assets\Resources\EditRequests\Pages;

use App\Enums\AssetEditRequestStatus;
use App\Filament\Assets\Resources\EditRequests\AssetEditRequestResource;
use App\Models\AssetEditRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListAssetEditRequests extends ListRecords
{
    protected static string $resource = AssetEditRequestResource::class;

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];

        if (AssetEditRequestResource::userIsAssetManager()) {
            $tabs = ['pending' => Tab::make('Pending')
                ->query(fn ($query) => $query->where('status', AssetEditRequestStatus::Pending))
                ->badge(fn () => AssetEditRequest::query()->where('status', AssetEditRequestStatus::Pending)->count())
                ->badgeColor('warning'),
                ...$tabs];
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return AssetEditRequestResource::userIsAssetManager() ? 'pending' : 'all';
    }
}
