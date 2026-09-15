<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Roll-call status for a bureau_minutes_attendance row — Council rows
 * use all three; Secretariat rows only ever use Present/Absent (a
 * simple checkbox in the UI, see RecordMinutes's attendance card).
 */
enum BureauAttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    case OnLeave = 'on_leave';

    public function getLabel(): string
    {
        return __('bureau.minutes.attendance_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Present => 'primary',
            self::Absent => 'danger',
            self::OnLeave => 'accent',
        };
    }
}
