<?php

namespace App\Filament\Assets\Resources\Audits\Pages;

use App\Filament\Assets\Resources\Audits\AssetAuditSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetAuditSessions extends ListRecords
{
    protected static string $resource = AssetAuditSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Start Audit')
                ->visible(fn (): bool => AssetAuditSessionResource::canCreate()),
        ];
    }
}
