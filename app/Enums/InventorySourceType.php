<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What document (if any) caused a stock_movements row — spec 6.4's
 * source_type column. Paired with source_id/source_line_id/source_no
 * on the movement itself.
 */
enum InventorySourceType: string implements HasColor, HasLabel
{
    case Grn = 'GRN';
    case Issue = 'ISSUE';
    case Adjustment = 'ADJUSTMENT';
    case Return = 'RETURN';
    case Opening = 'OPENING';
    case Reversal = 'REVERSAL';

    public function getLabel(): string
    {
        return match ($this) {
            self::Grn => 'Goods Receipt',
            self::Issue => 'Issue Request',
            self::Adjustment => 'Adjustment',
            self::Return => 'Return',
            self::Opening => 'Opening Balance',
            self::Reversal => 'Reversal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Grn => 'success',
            self::Issue => 'danger',
            self::Adjustment => 'warning',
            self::Return => 'info',
            self::Opening => 'gray',
            self::Reversal => 'danger',
        };
    }
}
