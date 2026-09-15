<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InventoryPriority: string implements HasColor, HasLabel
{
    case Low = 'LOW';
    case Normal = 'NORMAL';
    case Urgent = 'URGENT';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::Urgent => 'Urgent',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'info',
            self::Urgent => 'danger',
        };
    }
}
