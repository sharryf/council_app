<?php

namespace App\Http\Controllers\Bureau;

use App\Enums\BureauRole;
use App\Http\Controllers\Controller;
use App\Models\BureauMeeting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams one of a meeting's generated PDFs (the Meeting Request letter
 * or the agenda) from the private `local` disk — same gate as
 * AgendaAttachmentController: anyone holding any Bureau role, not just
 * the meeting's own creator or the President. Served inline (not as an
 * attachment) so the browser's own PDF viewer opens it directly in the
 * tab instead of forcing a save-to-disk prompt.
 */
class MeetingPdfDownloadController extends Controller
{
    public function show(BureauMeeting $meeting, string $type): StreamedResponse
    {
        $user = auth()->user();
        $isParty = $user && collect(BureauRole::cases())->contains(fn (BureauRole $role): bool => $user->hasBureauRole($role));
        abort_unless($isParty, 403);

        [$path, $filename] = match ($type) {
            'request' => [$meeting->meeting_request_pdf_path, 'meeting-request.pdf'],
            'agenda' => [$meeting->agenda_pdf_path, 'meeting-agenda.pdf'],
            default => abort(404),
        };

        abort_unless(filled($path), 404);

        return Storage::disk('local')->response($path, $filename);
    }
}
