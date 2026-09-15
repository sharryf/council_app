<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Spec 8.5: Good lines create a RETURN movement and increase on_hand;
 * Damaged lines are recorded but create no movement.
 */
enum InventoryReturnCondition: string implements HasColor, HasLabel
{
    case Good = 'GOOD';
    case Damaged = 'DAMAGED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Damaged => 'Damaged',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Good => 'success',
            self::Damaged => 'danger',
        };
    }
}
