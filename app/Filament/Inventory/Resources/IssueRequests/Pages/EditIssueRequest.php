<?php

namespace App\Filament\Inventory\Resources\IssueRequests\Pages;

use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIssueRequest extends EditRecord
{
    protected static string $resource = IssueRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => IssueRequestResource::canDelete($this->record)),
            IssueRequestResource::submitAction(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
