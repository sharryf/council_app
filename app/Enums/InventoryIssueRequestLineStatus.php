<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InventoryIssueRequestLineStatus: string implements HasColor, HasLabel
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case PartiallyIssued = 'PARTIALLY_ISSUED';
    case Issued = 'ISSUED';
    case Cancelled = 'CANCELLED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::PartiallyIssued => 'Partially Issued',
            self::Issued => 'Issued',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Approved => 'warning',
            self::Rejected => 'danger',
            self::PartiallyIssued => 'warning',
            self::Issued => 'success',
            self::Cancelled => 'danger',
        };
    }
}
