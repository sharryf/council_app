<?php

namespace App\Filament\Bureau\Resources\Meetings\Pages;

use App\Filament\Bureau\Resources\Meetings\MeetingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMeetings extends ListRecords
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('bureau.meeting.actions.add')),
        ];
    }
}
