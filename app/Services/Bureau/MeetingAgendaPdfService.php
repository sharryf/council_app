<?php

namespace App\Services\Bureau;

use App\Models\BureauMeeting;

/**
 * Renders the meeting agenda as HTML (Dhivehi/RTL, see the Blade view)
 * and converts it to a PDF via headless Chrome (spatie/browsershot) —
 * chosen deliberately over the FPDF/FPDI library Document Signing uses,
 * since FPDF has no text-shaping support and would render Thaana as
 * broken/disconnected glyphs. Chrome's own text engine renders it
 * correctly (verified live before building this). See
 * BureauPdfRenderer for the shared render/header/footer pipeline.
 */
class MeetingAgendaPdfService
{
    public function __construct(private readonly BureauPdfRenderer $renderer) {}

    public function generate(BureauMeeting $meeting): string
    {
        // agendaItems.creator.bureauRoles is needed by
        // BureauMeeting::groupedAgendaItems() to bucket regular items
        // by who submitted them.
        $meeting->loadMissing('attendees', 'agendaItems.creator.bureauRoles');

        $relativePath = "bureau/meetings/{$meeting->id}/agenda.pdf";
        $this->renderer->render('bureau.pdf.meeting-agenda', ['meeting' => $meeting], $relativePath);

        return $relativePath;
    }
}
