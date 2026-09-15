<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SignerStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Signed => 'Signed',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'muted',
            self::Signed => 'primary',
            self::Rejected => 'danger',
        };
    }
}
