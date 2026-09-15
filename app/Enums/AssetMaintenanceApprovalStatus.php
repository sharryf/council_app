<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Validates the maintenance RECORD, not the asset's status — approving
 * does not change assets.status, and rejecting does not restore it
 * either (implementation plan section 3.6). Independent of whether the
 * record has been closed out yet (see AssetMaintenanceRecord::isOpen()).
 */
enum AssetMaintenanceApprovalStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
