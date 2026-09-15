<?php

namespace App\Filament\Bureau\Resources\AgendaItems\Pages;

use App\Filament\Bureau\Resources\AgendaItems\AgendaItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgendaItems extends ListRecords
{
    protected static string $resource = AgendaItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('bureau.agenda.actions.add')),
        ];
    }
}
