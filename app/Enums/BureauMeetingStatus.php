<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BureauMeetingStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Scheduled = 'scheduled';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return __('bureau.meeting.status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::PendingApproval => 'accent',
            self::Scheduled => 'primary',
            self::Rejected => 'danger',
        };
    }
}
