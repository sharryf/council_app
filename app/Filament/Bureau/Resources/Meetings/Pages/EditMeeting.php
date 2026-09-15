<?php

namespace App\Filament\Bureau\Resources\Meetings\Pages;

use App\Filament\Bureau\Resources\Meetings\MeetingResource;
use App\Filament\Bureau\Resources\Meetings\Schemas\MeetingForm;
use App\Models\BureauMeeting;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;

/**
 * Only reachable while Draft or Rejected, by a Bureau Admin — see
 * MeetingResource::canEdit(). Once sent for approval, changes go
 * through the President's approve/reject actions instead (see
 * MeetingsTable), not this page.
 */
class EditMeeting extends EditRecord
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('bureau.meeting.actions.delete')),
        ];
    }

    /**
     * Filament's edit-form fill doesn't consult a field's ->default()
     * for keys missing from the record (that's create-only behavior)
     * — so the segmented date fields (see MeetingForm) have to be
     * populated here, decomposed from the record's own scheduled_at.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (filled($data['scheduled_at'] ?? null)) {
            $scheduledAt = Carbon::parse($data['scheduled_at']);
            $data['scheduled_day'] = $scheduledAt->day;
            $data['scheduled_month'] = $scheduledAt->month;
            $data['scheduled_year'] = $scheduledAt->year;
            $data['scheduled_time'] = $scheduledAt->format('H:i');
        }

        return $data;
    }

    /**
     * The record's own term/meeting numbers stay fixed on edit (see
     * MeetingForm — they're no longer form fields) — only the type may
     * change, so the name is rebuilt from those with the new type.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = MeetingForm::combineScheduledAt($data);

        $data['name'] = BureauMeeting::buildName($this->record->term_number, $this->record->meeting_number, $data['type'] ?? null);

        return $data;
    }
}
