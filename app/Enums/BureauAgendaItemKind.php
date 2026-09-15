<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Regular items come from the normal proposal/approval pool (see
 * BureauAgendaItem::scopeAvailableForMeeting()). AgendaPassing and
 * MinutesPassing are procedural items the system creates itself when a
 * meeting is created (see CreateMeeting::afterCreate()) — the standing
 * "pass this meeting's own agenda" motion, and "pass the minutes of a
 * specific past meeting" motion (see BureauAgendaItem::relatedMeeting()
 * for which past meeting). Both are still ordinary BureauAgendaItem
 * rows that get voted on and minuted exactly like any other item.
 */
enum BureauAgendaItemKind: string implements HasColor, HasLabel
{
    case Regular = 'regular';
    case AgendaPassing = 'agenda_passing';
    case MinutesPassing = 'minutes_passing';

    public function getLabel(): string
    {
        return __('bureau.agenda.kind.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Regular => 'gray',
            self::AgendaPassing => 'primary',
            self::MinutesPassing => 'info',
        };
    }
}
