<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\Assets\AssetQrCodeService;
use Illuminate\Http\Response;

/**
 * Authenticated-only QR PNG for a single asset — any of the three
 * AssetRoles may view (same gate as AssetAttachmentController). This is
 * the "Download QR" action on the asset's View page; the printable
 * label (AssetLabelController) embeds the same image inline instead.
 */
class AssetQrCodeController extends Controller
{
    public function show(Asset $asset, AssetQrCodeService $qrCodes): Response
    {
        $user = auth()->user();
        abort_unless($user && $user->assetRoleList() !== [], 403);

        $result = $qrCodes->png($asset, 600);

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Content-Disposition' => "attachment; filename=\"{$asset->asset_tag}-qr.png\"",
        ]);
    }
}
