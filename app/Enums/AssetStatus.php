<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * An asset's lifecycle stage. There's no separate "lifecycle stage"
 * field per the spec — lifecycle *is* this value changing over time,
 * tracked in asset_history. "Transfer Pending" is deliberately NOT a
 * member here — it's derived from an open AssetTransferRequest and
 * shown as a badge alongside the real status (see the implementation
 * plan's ยง2.1) so a transfer in flight never clobbers what the asset's
 * real state actually is. See section 2.1 of the plan.
 */
enum AssetStatus: string implements HasColor, HasLabel
{
    case InUse = 'in_use';
    case Damaged = 'damaged';
    case Auctioned = 'auctioned';
    case Disposed = 'disposed';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::InUse => 'In Use',
            self::Damaged => 'Damaged',
            self::Auctioned => 'Auctioned',
            self::Disposed => 'Disposed',
            self::Lost => 'Lost',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InUse => 'success',
            self::Damaged => 'warning',
            self::Auctioned => 'gray',
            self::Disposed => 'gray',
            self::Lost => 'danger',
        };
    }

    /**
     * Disposed and Auctioned both mean the asset has left the
     * council's possession — block new transfer requests and
     * maintenance records against either (implementation plan section
     * 8.3).
     */
    public function isTerminal(): bool
    {
        return $this === self::Disposed || $this === self::Auctioned;
    }
}
