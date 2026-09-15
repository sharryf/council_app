<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BureauVoteChoice: string implements HasColor, HasLabel
{
    case Yes = 'yes';
    case No = 'no';

    public function getLabel(): string
    {
        return __('bureau.minutes.vote.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Yes => 'primary',
            self::No => 'danger',
        };
    }
}
