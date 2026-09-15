<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Spec 8.2's state diagram: Draft -> Submitted -> Approved ->
 * PartiallyIssued -> Issued, with Rejected/Cancelled/Expired branches
 * off Submitted/Approved. Editable only in Draft (BR-20).
 */
enum InventoryIssueRequestStatus: string implements HasColor, HasLabel
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Approved = 'APPROVED';
    case PartiallyIssued = 'PARTIALLY_ISSUED';
    case Issued = 'ISSUED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::PartiallyIssued => 'Partially Issued',
            self::Issued => 'Issued',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::Approved => 'warning',
            self::PartiallyIssued => 'warning',
            self::Issued => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'danger',
            self::Expired => 'danger',
        };
    }
}
