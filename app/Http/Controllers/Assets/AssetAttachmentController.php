<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\AssetAttachment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves any asset_attachments row through a private, authenticated
 * route — same pattern as App\Http\Controllers\Inventory\InventoryAttachmentController.
 * Any of the three AssetRoles may view (spec section 4: "View asset
 * list / detail" is checked for Admin/Manager/Viewer alike).
 */
class AssetAttachmentController extends Controller
{
    public function show(AssetAttachment $attachment): StreamedResponse|Response
    {
        $user = auth()->user();
        abort_unless($user && $user->assetRoleList() !== [], 403);

        // Photos render inline (used as <img src>). Documents now open
        // inline too — a PDF or image previews right in the browser tab;
        // the browser itself falls back to downloading anything it can't
        // render natively (e.g. DOCX/XLSX), so this is a strict upgrade
        // over always forcing a download.
        if ($attachment->kind === 'photo') {
            return Storage::disk('local')->response($attachment->file_path, $attachment->file_name);
        }

        $downloadName = $attachment->document_name
            ? "{$attachment->document_name}.".pathinfo($attachment->file_name, PATHINFO_EXTENSION)
            : $attachment->file_name;

        return Storage::disk('local')->response($attachment->file_path, $downloadName);
    }
}
