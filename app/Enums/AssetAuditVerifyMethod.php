<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssetAuditVerifyMethod: string implements HasLabel
{
    case QrScan = 'qr_scan';
    case ManualCheck = 'manual_check';

    public function getLabel(): string
    {
        return match ($this) {
            self::QrScan => 'QR scan',
            self::ManualCheck => 'Manual check',
        };
    }
}
