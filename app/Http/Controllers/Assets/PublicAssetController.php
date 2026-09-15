<?php

namespace App\Http\Controllers\Assets;

use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The no-login QR landing page (spec section 9 / implementation plan
 * section 6.7). Looked up by public_token, never by id — an unknown
 * token gets the same generic 404 as any other missing route, so
 * scanning around never reveals whether a token exists (implementation
 * plan section 2.3/8.25).
 */
class PublicAssetController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $asset = Asset::query()->where('public_token', $token)->first();

        abort_unless($asset !== null, 404);

        // Already logged in and holds an asset role — send them
        // straight to the full authenticated detail page instead of
        // the stripped-down public one (spec section 6.7).
        if (($user = auth()->user()) && $user->assetRoleList() !== []) {
            return redirect(AssetResource::getUrl('view', ['record' => $asset]));
        }

        $asset->loadMissing('room.building');

        return view('assets.public-page', [
            'asset' => $asset,
            'photoUrl' => $asset->photo_attachment_id
                ? route('assets.public.photo', $asset->public_token)
                : null,
        ]);
    }

    /**
     * The public page's photo, served by public_token — deliberately
     * NOT the authenticated assets.attachments.show route (that would
     * both require login, breaking the no-login view, and leak a
     * sequential attachment id publicly). Implementation plan section
     * 3.4's "proxy the image through a rate-limited public endpoint
     * keyed by public_token" — the route itself carries the rate limit.
     */
    public function photo(string $token): StreamedResponse
    {
        $asset = Asset::query()->where('public_token', $token)->first();

        abort_unless($asset !== null && $asset->photo_attachment_id !== null, 404);

        $asset->loadMissing('photoAttachment');

        return Storage::disk('local')->response($asset->photoAttachment->file_path, $asset->photoAttachment->file_name);
    }

    /**
     * JSON counterpart to show() (spec section 5's
     * GET /api/public/assets/{public_token}) — exactly the same five
     * fields, for a scanning app that wants data instead of HTML.
     */
    public function json(string $token): JsonResponse
    {
        $asset = Asset::query()->where('public_token', $token)->first();

        abort_unless($asset !== null, 404);

        $asset->loadMissing('room.building');

        return response()->json([
            'name' => $asset->name,
            'asset_tag' => $asset->asset_tag,
            'status' => $asset->status->getLabel(),
            'photo_url' => $asset->photo_attachment_id ? route('assets.public.photo', $asset->public_token) : null,
            'building' => $asset->room->building->name,
            'room' => $asset->room->name,
        ]);
    }
}
