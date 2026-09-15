<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Every quantity change in the ledger is one of these (spec 6.4). Each
 * carries its own fixed direction — StockMovementService asks the type
 * for it rather than trusting the caller to pass a matching direction
 * separately.
 */
enum InventoryMovementType: string implements HasColor, HasLabel
{
    case Opening = 'OPENING';
    case Receipt = 'RECEIPT';
    case Issue = 'ISSUE';
    case Return = 'RETURN';
    case AdjustIn = 'ADJUST_IN';
    case AdjustOut = 'ADJUST_OUT';
    case TransferIn = 'TRANSFER_IN';
    case TransferOut = 'TRANSFER_OUT';
    case ReversalIn = 'REVERSAL_IN';
    case ReversalOut = 'REVERSAL_OUT';

    public function direction(): int
    {
        return match ($this) {
            self::Opening, self::Receipt, self::Return, self::AdjustIn, self::TransferIn, self::ReversalIn => 1,
            self::Issue, self::AdjustOut, self::TransferOut, self::ReversalOut => -1,
        };
    }

    /**
     * The opposite movement type — what reverse() writes to undo this
     * one.
     */
    public function reversalType(): self
    {
        return match ($this) {
            self::Opening, self::Receipt, self::Return, self::AdjustIn, self::TransferIn => self::ReversalOut,
            self::Issue, self::AdjustOut, self::TransferOut => self::ReversalIn,
            self::ReversalIn, self::ReversalOut => throw new \LogicException('A reversal movement cannot itself be reversed.'),
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Opening => 'Opening Balance',
            self::Receipt => 'Receipt',
            self::Issue => 'Issue',
            self::Return => 'Return',
            self::AdjustIn => 'Adjustment (In)',
            self::AdjustOut => 'Adjustment (Out)',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
            self::ReversalIn => 'Reversal (In)',
            self::ReversalOut => 'Reversal (Out)',
        };
    }

    public function getColor(): string
    {
        return $this->direction() === 1 ? 'success' : 'danger';
    }
}
