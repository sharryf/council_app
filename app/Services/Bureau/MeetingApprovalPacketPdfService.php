<?php

namespace App\Services\Bureau;

use App\Models\BureauMeeting;

/**
 * Generates the Meeting Request letter sent to the President when a
 * meeting is submitted for approval (see
 * MeetingResource::sendForApprovalAction()) — distinct from
 * MeetingAgendaPdfService, which generates the agenda PDF later, only
 * once the meeting is actually approved/Scheduled (the council only
 * wants one agenda document, not a separate proposal draft). See
 * BureauPdfRenderer for the shared render/header/footer pipeline.
 *
 * The Blade view is a placeholder layout pending a reference template
 * from the council — swap the view content when that's available,
 * generateRequest() won't need to change.
 */
class MeetingApprovalPacketPdfService
{
    public function __construct(private readonly BureauPdfRenderer $renderer) {}

    public function generateRequest(BureauMeeting $meeting): string
    {
        $relativePath = "bureau/meetings/{$meeting->id}/request.pdf";
        $this->renderer->render('bureau.pdf.meeting-request', ['meeting' => $meeting], $relativePath);

        return $relativePath;
    }
}
