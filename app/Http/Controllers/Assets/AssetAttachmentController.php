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

        // Photos render inline (used as <img src>) — documents force a
        // download, since a browser may otherwise try to render a PDF
        // or DOCX in a way that isn't what the "Attachments" tab wants.
        if ($attachment->kind === 'photo') {
            return Storage::disk('local')->response($attachment->file_path, $attachment->file_name);
        }

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }
}
