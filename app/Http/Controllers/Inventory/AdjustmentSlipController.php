<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryRole;
use App\Http\Controllers\Controller;
use App\Models\InventoryStockAdjustment;
use App\Services\Inventory\InventoryPdfRenderer;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates (or regenerates, on every request) a printable, signed
 * record of a posted adjustment — same shape as IssueSlipController,
 * only available once the adjustment has actually been posted.
 */
class AdjustmentSlipController extends Controller
{
    public function show(InventoryStockAdjustment $adjustment, InventoryPdfRenderer $renderer): StreamedResponse
    {
        $user = auth()->user();
        $isParty = $user && collect(InventoryRole::cases())->contains(fn (InventoryRole $role): bool => $user->hasInventoryRole($role));
        abort_unless($isParty, 403);

        abort_unless($adjustment->posted_at !== null, 404);

        $relativePath = "inventory/adjustment-slips/{$adjustment->id}.pdf";

        $adjustment->loadMissing(['createdBy', 'approvedBy', 'lines.item.uom']);

        $renderer->render('inventory.pdf.adjustment-slip', [
            'adjustment' => $adjustment,
            'creatorSignature' => $this->signatureDataUri($adjustment->createdBy?->defaultSignaturePath()),
            'approvedSignature' => $this->signatureDataUri($adjustment->approvedBy?->defaultSignaturePath()),
        ], $relativePath);

        return Storage::disk('local')->response($relativePath, "Adjustment Slip — {$adjustment->adjustment_no}.pdf");
    }

    private function signatureDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $bytes = Storage::disk('local')->get($path);

        return $bytes ? 'data:image/png;base64,'.base64_encode($bytes) : null;
    }
}
