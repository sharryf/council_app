<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssetAuditVerifyMethod: string implements HasLabel
{
    case QrScan = 'qr_scan';
    case ManualCheck = 'manual_check';
    // Set only by ViewAssetAuditSession::foundInAnotherRoom()/
    // foundMisplaced() — distinct from the two plain-verification
    // methods above so Undo can tell them apart (undoing a location
    // change means also unwinding a transfer request or a "return it"
    // note, which Undo doesn't attempt).
    case FoundElsewhere = 'found_elsewhere';
    case Misplaced = 'misplaced';

    public function getLabel(): string
    {
        return match ($this) {
            self::QrScan => 'QR scan',
            self::ManualCheck => 'Manual check',
            self::FoundElsewhere => 'Found elsewhere — moved',
            self::Misplaced => 'Found elsewhere — misplaced',
        };
    }

    /**
     * Only a plain verification (no accompanying room-change decision)
     * can be undone — see ViewAssetAuditSession::undoVerification().
     */
    public function isUndoable(): bool
    {
        return $this === self::QrScan || $this === self::ManualCheck;
    }
}
