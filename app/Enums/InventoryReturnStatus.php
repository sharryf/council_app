<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Spec 8.5: Draft -> Posted -> Reversed. No approval step — see
 * InventoryStockReturn's own schema (no approved_by column) and the
 * spec's own API surface (no /approve endpoint for returns).
 */
enum InventoryReturnStatus: string implements HasColor, HasLabel
{
    case Draft = 'DRAFT';
    case Posted = 'POSTED';
    case Reversed = 'REVERSED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
            self::Reversed => 'Reversed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Posted => 'success',
            self::Reversed => 'danger',
        };
    }
}
