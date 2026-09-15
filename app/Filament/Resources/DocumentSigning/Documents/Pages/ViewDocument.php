<?php

namespace App\Filament\Resources\DocumentSigning\Documents\Pages;

use App\Filament\Resources\DocumentSigning\Documents\DocumentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DocumentResource::previewAction(),
            DocumentResource::submitAction(),
            DocumentResource::signAction(),
            DocumentResource::rejectAction(),
            DocumentResource::voidAction(),
            DocumentResource::downloadOriginalAction(),
            DocumentResource::downloadSignedAction(),
            DocumentResource::editAction(),
        ];
    }
}
