<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssetAcquisitionType: string implements HasLabel
{
    case Purchased = 'purchased';
    case Donated = 'donated';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchased => 'Purchased',
            self::Donated => 'Donated',
        };
    }
}
