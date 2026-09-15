<?php

namespace App\Http\Controllers\Bureau;

use App\Enums\BureauRole;
use App\Http\Controllers\Controller;
use App\Models\BureauAgendaItem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an agenda item's attachment from the private `local` disk —
 * not a public storage URL. Gated to anyone holding any Bureau role
 * (same "any participant may see the shared agenda list" rule as
 * AgendaItemResource::canAccess()), not just the item's own creator.
 */
class AgendaAttachmentController extends Controller
{
    public function show(BureauAgendaItem $agendaItem): StreamedResponse
    {
        $user = auth()->user();
        $isParty = $user && collect(BureauRole::cases())->contains(fn (BureauRole $role): bool => $user->hasBureauRole($role));
        abort_unless($isParty, 403);

        abort_unless($agendaItem->hasAttachment(), 404);

        return Storage::disk('local')->download(
            $agendaItem->attachment_path,
            $agendaItem->attachment_original_name ?? 'attachment',
        );
    }
}
