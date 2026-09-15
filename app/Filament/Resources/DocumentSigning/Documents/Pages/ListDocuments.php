<?php

namespace App\Filament\Resources\DocumentSigning\Documents\Pages;

use App\Filament\Resources\DocumentSigning\Documents\DocumentResource;
use App\Filament\Resources\DocumentSigning\Documents\Widgets\PendingSignaturesWidget;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DocumentResource::createAction(),
        ];
    }

    /**
     * This module has no separate dashboard route — Documents is its
     * home page, so the pending-signature notification card (see
     * App\Filament\Resources\DocumentSigning\Documents\Widgets\PendingSignaturesWidget)
     * sits at the top of it.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            PendingSignaturesWidget::class,
        ];
    }

    /**
     * PendingSignaturesWidget's card dispatches this when clicked — it's
     * a separate Livewire component from this page, so it can't reach
     * $this->tableFilters directly. 'isActive' is the shape Filament's
     * boolean Filter::make() stores its state under (see
     * DocumentsTable's 'assigned_to_me' filter).
     */
    #[On('apply-pending-documents-filter')]
    public function applyPendingDocumentsFilter(): void
    {
        $this->tableFilters['assigned_to_me'] = ['isActive' => true];
        $this->resetPage();
    }
}
