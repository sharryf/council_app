<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssetAuditScopeType: string implements HasLabel
{
    case All = 'all';
    case Building = 'building';
    case Room = 'room';

    public function getLabel(): string
    {
        return match ($this) {
            self::All => 'All assets',
            self::Building => 'One building',
            self::Room => 'One room',
        };
    }
}
