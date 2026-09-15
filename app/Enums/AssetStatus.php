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
    case InStorage = 'in_storage';
    case UnderRepair = 'under_repair';
    case Retired = 'retired';
    case Disposed = 'disposed';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::InUse => 'In Use',
            self::InStorage => 'In Storage',
            self::UnderRepair => 'Under Repair',
            self::Retired => 'Retired',
            self::Disposed => 'Disposed',
            self::Lost => 'Lost',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InUse => 'success',
            self::InStorage => 'gray',
            self::UnderRepair => 'warning',
            self::Retired => 'muted',
            self::Disposed => 'gray',
            self::Lost => 'danger',
        };
    }

    /**
     * Disposed is effectively terminal (implementation plan section 8.3) —
     * block new transfer requests and maintenance records against it.
     */
    public function isTerminal(): bool
    {
        return $this === self::Disposed;
    }
}
