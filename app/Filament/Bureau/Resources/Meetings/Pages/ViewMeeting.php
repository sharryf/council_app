<?php

namespace App\Filament\Bureau\Resources\Meetings\Pages;

use App\Filament\Bureau\Resources\Meetings\MeetingResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * "When opened, meeting details can be seen [including] all invitees"
 * — see MeetingInfolist for the attendees/agenda-items breakdown.
 */
class ViewMeeting extends ViewRecord
{
    protected static string $resource = MeetingResource::class;

    /**
     * No page heading — the meeting's own name is already shown as a
     * bold, centered heading in the infolist itself (see
     * MeetingInfolist), so the generic "View Meeting" title would just
     * be a redundant second heading. Breadcrumbs and header actions
     * (Send for Approval, Approve, etc.) stay untouched.
     */
    public function getHeading(): string
    {
        return '';
    }

    protected function getHeaderActions(): array
    {
        return [
            MeetingResource::minutesAction(),
            MeetingResource::sendForApprovalAction(),
            MeetingResource::approveAction(),
            MeetingResource::rejectAction(),
        ];
    }
}
