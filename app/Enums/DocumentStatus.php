<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Signed = 'signed';
    case Rejected = 'rejected';
    case Voided = 'voided';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending signatures',
            self::Signed => 'Signed',
            self::Rejected => 'Rejected',
            self::Voided => 'Voided',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'muted',
            self::Pending => 'accent',
            self::Signed => 'primary',
            self::Rejected => 'danger',
            self::Voided => 'gray',
        };
    }
}
