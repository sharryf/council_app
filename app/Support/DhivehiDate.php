<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Shared "day Dhivehi-month year HH:MM" RTL date format — used
 * wherever a date/time is shown in the Bureau module's own UI (see
 * AgendaItemsTable's created_at column and MeetingsTable's
 * scheduled_at column), so the two stay in sync instead of drifting
 * with independently copy-pasted month-name arrays.
 */
class DhivehiDate
{
    /**
     * Public so form fields needing a month picker (see MeetingForm's
     * segmented date fields) can reuse the same names instead of
     * keeping a second copy in sync.
     */
    public const MONTHS = [
        1 => 'ޖެނުވަރީ', 2 => 'ފެބުރުވަރީ', 3 => 'މާރިޗް',
        4 => 'އެޕްރީލް', 5 => 'މެއި', 6 => 'ޖޫން',
        7 => 'ޖުލައި', 8 => 'އޮގަސްޓް', 9 => 'ސެޕްޓެންބަރު',
        10 => 'އޮކްޓޯބަރު', 11 => 'ނޮވެންބަރު', 12 => 'ޑިސެންބަރު',
    ];

    public static function html(CarbonInterface $date): string
    {
        return '<span dir="rtl">'.self::format($date).'</span>';
    }

    public static function format(CarbonInterface $date): string
    {
        return $date->day.' '.self::MONTHS[$date->month].' '.$date->year.' '.$date->format('H:i');
    }
}
