<?php

namespace App\Services\Assets;

use App\Models\Asset;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;

/**
 * Generates the QR code that encodes an asset's public URL (spec
 * section 9 / implementation plan section 2.3) — the QR always points
 * at /a/{public_token}, never a sequential id, so scanning it can never
 * be used to enumerate the asset register.
 */
class AssetQrCodeService
{
    public function publicUrl(Asset $asset): string
    {
        return route('assets.public.show', $asset->public_token);
    }

    public function png(Asset $asset, int $size = 300): ResultInterface
    {
        return (new Builder())->build(
            writer: new PngWriter(),
            data: $this->publicUrl($asset),
            size: $size,
            margin: 10,
        );
    }
}
