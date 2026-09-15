<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Spec 8.1: Draft -> Posted -> Reversed, extended with an admin
 * approval gate in front of the reversal step — Posted ->
 * PendingReversal (a Stock Admin proposes reversing, with a reason) ->
 * Reversed (an Admin approves it) or back to Posted (an Admin rejects
 * it). A posted GRN is immutable either way (BR-23) — corrected only by
 * reversal, never edited.
 */
enum InventoryGoodsReceiptStatus: string implements HasColor, HasLabel
{
    case Draft = 'DRAFT';
    case Posted = 'POSTED';
    case PendingReversal = 'PENDING_REVERSAL';
    case Reversed = 'REVERSED';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Posted',
            self::PendingReversal => 'Pending Reversal Approval',
            self::Reversed => 'Reversed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Posted => 'success',
            self::PendingReversal => 'warning',
            self::Reversed => 'danger',
        };
    }
}
