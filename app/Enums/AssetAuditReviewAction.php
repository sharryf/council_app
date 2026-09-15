<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssetAuditReviewAction: string implements HasLabel
{
    case MarkedLost = 'marked_lost';
    case KeptAsIs = 'kept_as_is';
    case Dismissed = 'dismissed';
    case LocationCorrected = 'location_corrected';

    public function getLabel(): string
    {
        return match ($this) {
            self::MarkedLost => 'Marked Lost',
            self::KeptAsIs => 'Kept As Is',
            self::Dismissed => 'Dismissed',
            self::LocationCorrected => 'Location Corrected',
        };
    }
}
