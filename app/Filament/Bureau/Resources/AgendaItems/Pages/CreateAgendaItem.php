<?php

namespace App\Filament\Bureau\Resources\AgendaItems\Pages;

use App\Enums\BureauAgendaStatus;
use App\Enums\BureauRole;
use App\Filament\Bureau\Resources\AgendaItems\AgendaItemResource;
use App\Filament\Bureau\Resources\AgendaItems\Concerns\InteractsWithAttachment;
use Filament\Resources\Pages\CreateRecord;

class CreateAgendaItem extends CreateRecord
{
    use InteractsWithAttachment;

    protected static string $resource = AgendaItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->extractAttachmentFields($data);
        $user = auth()->user();
        $data['created_by'] = $user->id;

        // President's items auto-approve upon creation
        if ($user->hasBureauRole(BureauRole::President)) {
            $data['status'] = BureauAgendaStatus::Approved;
            $data['reviewed_by'] = $user->id;
            $data['reviewed_at'] = now();
        } else {
            $data['status'] = BureauAgendaStatus::Entered;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
