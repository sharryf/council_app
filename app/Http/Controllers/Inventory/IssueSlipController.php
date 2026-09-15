<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryRole;
use App\Http\Controllers\Controller;
use App\Models\InventoryIssueRequest;
use App\Services\Inventory\InventoryPdfRenderer;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates (or regenerates, on every request — cheap enough, and
 * avoids a schema change to persist a path the spec doesn't require
 * keeping) spec 10.6's printable issue slip and streams it inline for
 * viewing/printing, same auth-gated-route shape as
 * InventoryAttachmentController.
 */
class IssueSlipController extends Controller
{
    public function show(InventoryIssueRequest $issueRequest, InventoryPdfRenderer $renderer): StreamedResponse
    {
        $user = auth()->user();
        $isParty = $user && collect(InventoryRole::cases())->contains(fn (InventoryRole $role): bool => $user->hasInventoryRole($role));
        abort_unless($isParty, 403);

        abort_unless($issueRequest->issued_at !== null, 404);

        $relativePath = "inventory/issue-slips/{$issueRequest->id}.pdf";

        $issueRequest->loadMissing([
            'requester', 'recipient', 'location', 'issuedByUser', 'approver', 'lines.item.uom',
            'receipts.issuedByUser', 'receipts.movements.item',
        ]);

        $renderer->render('inventory.pdf.issue-slip', [
            'request' => $issueRequest,
            'issuerSignature' => $this->signatureDataUri($issueRequest->issuedByUser?->defaultSignaturePath()),
            'approverSignature' => $this->signatureDataUri($issueRequest->approver?->defaultSignaturePath()),
            'receiverSignature' => $this->signatureDataUri($issueRequest->receiver_signature_path),
            'receiptSignatures' => $issueRequest->receipts
                ->mapWithKeys(fn ($receipt) => [$receipt->id => $this->signatureDataUri($receipt->receiver_signature_path)]),
            'receiptIssuerSignatures' => $issueRequest->receipts
                ->mapWithKeys(fn ($receipt) => [$receipt->id => $this->signatureDataUri($receipt->issuedByUser?->defaultSignaturePath())]),
        ], $relativePath);

        return Storage::disk('local')->response($relativePath, "{$issueRequest->request_no}.pdf");
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
