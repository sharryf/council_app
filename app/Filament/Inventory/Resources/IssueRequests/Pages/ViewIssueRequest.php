<?php

namespace App\Filament\Inventory\Resources\IssueRequests\Pages;

use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewIssueRequest extends ViewRecord
{
    protected static string $resource = IssueRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('slip')
                ->label('Issue Slip')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->url(fn (): string => route('inventory.issue-requests.slip', $this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->record->issued_at)),
            EditAction::make()
                ->visible(fn (): bool => IssueRequestResource::canEdit($this->record)),
            IssueRequestResource::submitAction(),
            IssueRequestResource::approveAction(),
            IssueRequestResource::rejectAction(),
            IssueRequestResource::returnForEditAction(),
            IssueRequestResource::issueAction(),
            IssueRequestResource::cancelAction(),
        ];
    }
}
