<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Computed once, at session close, for every item still unverified at
 * that point (implementation plan section 3.8) — never set at verify
 * time. Purely informational: no outcome here ever triggers a review
 * queue or mutates the asset — a room correction goes through
 * ViewAssetAuditSession::foundInAnotherRoom()'s ordinary transfer
 * request instead, resolved (or not) independently of this outcome.
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
            self::Missing => 'Not Found in this Audit',
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
}
