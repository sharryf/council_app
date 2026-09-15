<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Spec 8.4: the approval gate is by type, not value — Damage/Loss/
 * Correction get scrutiny (require_approval_for_adjustments), StockTake
 * can post directly (its own stock_take_requires_approval setting).
 */
enum InventoryAdjustmentType: string implements HasColor, HasLabel
{
    case StockTake = 'STOCK_TAKE';
    case Damage = 'DAMAGE';
    case Loss = 'LOSS';
    case Expiry = 'EXPIRY';
    case OpeningBalance = 'OPENING_BALANCE';
    case Correction = 'CORRECTION';

    public function getLabel(): string
    {
        return match ($this) {
            self::StockTake => 'Stock Take',
            self::Damage => 'Damage',
            self::Loss => 'Loss',
            self::Expiry => 'Expiry',
            self::OpeningBalance => 'Opening Balance',
            self::Correction => 'Correction',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::StockTake => 'info',
            self::Damage => 'danger',
            self::Loss => 'danger',
            self::Expiry => 'warning',
            self::OpeningBalance => 'gray',
            self::Correction => 'warning',
        };
    }

    /**
     * Damage/Loss/Correction write-offs get scrutiny; routine count
     * corrections and one-time setup types don't (spec 8.4's own
     * framing — "the gate is the adjustment type").
     */
    public function requiresApprovalGate(): bool
    {
        return match ($this) {
            self::Damage, self::Loss, self::Correction => true,
            default => false,
        };
    }
}
