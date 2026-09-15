<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BureauAgendaStatus: string implements HasColor, HasLabel
{
    case Entered = 'entered';
    case Approved = 'approved';
    case AddedToMeeting = 'added_to_meeting';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __('bureau.agenda.status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Entered => 'accent',
            self::Approved => 'primary',
            self::AddedToMeeting => 'success',
            self::Rejected => 'danger',
        };
    }
}
