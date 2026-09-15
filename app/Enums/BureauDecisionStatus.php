<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BureauDecisionStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return __('bureau.minutes.decision_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'accent',
            self::Passed => 'primary',
            self::Failed => 'danger',
            self::Skipped => 'muted',
        };
    }
}
