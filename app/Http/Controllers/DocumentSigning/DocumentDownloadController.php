<?php

namespace App\Http\Controllers\DocumentSigning;

use App\Enums\DocumentSigningRole;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams document files from the private `local` disk. Not exposed as
 * a public storage URL — every request is checked against the current
 * user being the uploader, a listed signer, holding this module's
 * Viewer role (see App\Enums\DocumentSigningRole — "see every uploaded
 * document, not just their own"), or an admin.
 */
class DocumentDownloadController extends Controller
{
    public function original(Document $document): StreamedResponse
    {
        $this->authorizeAccess($document);

        return Storage::disk('local')->download($document->file_path, $document->file_original_name);
    }

    public function signed(Document $document): StreamedResponse
    {
        $this->authorizeAccess($document);

        abort_unless($document->signed_file_path, 404);

        return Storage::disk('local')->download(
            $document->signed_file_path,
            'Signed - '.$document->file_original_name,
        );
    }

    private function authorizeAccess(Document $document): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        $isParty = $user->hasDocumentSigningRole(DocumentSigningRole::Viewer)
            || $document->uploaded_by === $user->id
            || $document->signers()->where('user_id', $user->id)->exists();

        abort_unless($isParty, 403);
    }
}
