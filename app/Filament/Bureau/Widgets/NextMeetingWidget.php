<?php

namespace App\Filament\Bureau\Widgets;

use App\Models\BureauMeeting;
use Filament\Widgets\Widget;

/**
 * "Always [the] next upcoming meeting will be displayed at home" — the
 * Bureau dashboard's own card (see BureauMeeting::nextUpcoming(),
 * shared with MeetingsTable's highlight of the same meeting).
 */
class NextMeetingWidget extends Widget
{
    protected string $view = 'filament.bureau.widgets.next-meeting';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function getNextMeeting(): ?BureauMeeting
    {
        return BureauMeeting::nextUpcoming();
    }
}
