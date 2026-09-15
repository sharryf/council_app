<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Which roster a bureau_minutes_attendance row belongs to — stored
 * explicitly at write time (not re-derived from the user's current
 * bureau roles on every read) so it stays stable even if someone's
 * roles change after the meeting. See
 * BureauMeetingMinutes::councilAttendance()/secretariatAttendance().
 */
enum BureauMinutesAttendanceGroup: string implements HasLabel
{
    case Council = 'council';
    case Secretariat = 'secretariat';

    public function getLabel(): string
    {
        return __('bureau.minutes.attendance_group.'.$this->value);
    }
}
