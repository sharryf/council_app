<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\Assets\AssetQrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Printable labels (spec section 6.7) — plain print-styled HTML rather
 * than a generated PDF; the browser's own print dialog (or "Save as
 * PDF") handles output, matching the spec's "HTML or PDF" allowance
 * without adding a Browsershot render for something this simple.
 */
class AssetLabelController extends Controller
{
    private function authorize(): void
    {
        $user = auth()->user();
        abort_unless($user && $user->assetRoleList() !== [], 403);
    }

    public function show(Asset $asset, AssetQrCodeService $qrCodes): View
    {
        $this->authorize();

        return view('assets.label-single', [
            'asset' => $asset,
            'qrDataUri' => $qrCodes->png($asset, 240)->getDataUri(),
        ]);
    }

    /**
     * Bulk print sheet for a set of assets selected from the list —
     * matters when tagging ~500 assets initially (spec section 6.7).
     */
    public function bulk(Request $request, AssetQrCodeService $qrCodes): View
    {
        $this->authorize();

        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter()
            ->values();

        abort_if($ids->isEmpty(), 404);

        $assets = Asset::query()->whereKey($ids)->orderBy('name')->get();

        abort_if($assets->isEmpty(), 404);

        $labels = $assets->map(fn (Asset $asset): array => [
            'asset' => $asset,
            'qrDataUri' => $qrCodes->png($asset, 200)->getDataUri(),
        ]);

        return view('assets.label-bulk', ['labels' => $labels]);
    }
}
