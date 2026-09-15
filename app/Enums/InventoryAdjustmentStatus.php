<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Spec 8.4: Draft -> PendingApproval -> Posted -> Reversed. Not every
 * adjustment passes through PendingApproval — see
 * InventoryAdjustmentType::requiresApprovalGate() and the
 * stock_take_requires_approval setting.
 */
enum InventoryAdjustmentStatus: string implements HasColor, HasLabel
{
    case Draft = 'DRAFT';
    case PendingApproval = 'PENDING_APPROVAL';
    case Posted = 'POSTED';
    case Reversed = 'REVERSED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Posted => 'Posted',
            self::Reversed => 'Reversed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'warning',
            self::Posted => 'success',
            self::Reversed => 'danger',
        };
    }
}
