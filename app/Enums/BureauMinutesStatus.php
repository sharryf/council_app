<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * - Draft: the meeting is live, Bureau Admin is actively recording.
 * - Review: meeting ended — any attendee may edit any comment (see
 *   BureauMinutesComment) before the President reviews it.
 * - Approved: President signed off — a final PDF was generated and
 *   handed to Document Signing with every attendee as a signer.
 * - Signed: the Document Signing record came back fully signed (see
 *   BureauMeetingMinutes::syncFromDocument()).
 */
enum BureauMinutesStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Signed = 'signed';

    public function getLabel(): string
    {
        return __('bureau.minutes.status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'accent',
            self::Review => 'muted',
            self::Approved => 'primary',
            self::Signed => 'primary',
        };
    }
}
