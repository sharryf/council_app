<?php

namespace App\Filament\Assets\Resources\Rooms\Pages;

use App\Filament\Assets\Resources\Rooms\AssetRoomResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetRooms extends ListRecords
{
    protected static string $resource = AssetRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => AssetRoomResource::canCreate()),
        ];
    }
}
