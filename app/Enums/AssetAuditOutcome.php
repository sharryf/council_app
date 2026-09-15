<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Computed once, at session close, for every item (implementation plan
 * section 3.8) — never set at verify time.
 */
enum AssetAuditOutcome: string implements HasColor, HasLabel
{
    case Verified = 'verified';
    case LocationMismatch = 'location_mismatch';
    case Missing = 'missing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Verified => 'Verified',
            self::LocationMismatch => 'Location Mismatch',
            self::Missing => 'Missing',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Verified => 'success',
            self::LocationMismatch => 'warning',
            self::Missing => 'danger',
        };
    }

    public function needsReview(): bool
    {
        return $this !== self::Verified;
    }
}
