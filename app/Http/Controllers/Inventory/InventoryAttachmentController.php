<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryRole;
use App\Http\Controllers\Controller;
use App\Models\InventoryAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves any inventory_attachments row through a private, authenticated
 * route — one controller for every entity_type (GRN today, others as
 * later phases add them), same pattern as
 * App\Http\Controllers\Bureau\AgendaAttachmentController.
 */
class InventoryAttachmentController extends Controller
{
    public function show(InventoryAttachment $attachment): StreamedResponse
    {
        $user = auth()->user();
        $isParty = $user && collect(InventoryRole::cases())->contains(fn (InventoryRole $role): bool => $user->hasInventoryRole($role));
        abort_unless($isParty, 403);

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }
}
