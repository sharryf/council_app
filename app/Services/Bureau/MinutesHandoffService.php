<?php

namespace App\Services\Bureau;

use App\Enums\BureauAttendanceStatus;
use App\Enums\BureauMinutesStatus;
use App\Enums\DocumentStatus;
use App\Enums\SigningMode;
use App\Models\BureauMeetingMinutes;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentSigning\DocumentSigningService;
use Illuminate\Support\Facades\DB;

/**
 * The final step of the minutes workflow: once the Council President
 * approves the drafted minutes, this renders the definitive PDF (see
 * BureauPdfRenderer for the shared Browsershot/headless-Chrome
 * pipeline) and hands it to the existing Document Signing module —
 * "Anything requires signatures will use previous Document Signing
 * Module" — with every council member actually marked Present as a
 * Parallel signer (not every invited attendee — secretariat/listeners
 * never sign, they don't vote or decide), then submits it for signing
 * immediately.
 */
class MinutesHandoffService
{
    public function __construct(private readonly BureauPdfRenderer $renderer) {}

    public function approveAndSendForSigning(BureauMeetingMinutes $minutes, User $reviewer): Document
    {
        $minutes->loadMissing([
            'meeting.attendees',
            'meeting.agendaItems',
            'chair',
            'councilAttendance',
            'secretariatAttendance',
            'listeners',
            'comments.speaker',
            'decisionRequests.votes.voter',
        ]);

        $meeting = $minutes->meeting;
        $signers = $minutes->councilAttendance->filter(
            fn (User $user): bool => $user->pivot->status === BureauAttendanceStatus::Present->value,
        )->values();

        $relativePath = "bureau/minutes/{$minutes->id}/minutes.pdf";
        $this->renderer->render('bureau.pdf.meeting-minutes', ['meeting' => $meeting, 'minutes' => $minutes], $relativePath);

        return DB::transaction(function () use ($minutes, $meeting, $reviewer, $relativePath, $signers): Document {
            $document = Document::create([
                'title' => __('bureau.minutes.pdf.title').' — '.$meeting->name,
                'file_path' => $relativePath,
                'file_original_name' => 'minutes.pdf',
                'uploaded_by' => $reviewer->id,
                'signing_mode' => SigningMode::Parallel,
                'status' => DocumentStatus::Draft,
            ]);

            foreach ($signers as $index => $attendee) {
                $document->signers()->create([
                    'user_id' => $attendee->id,
                    'order' => $index + 1,
                    'role_label' => 'Signer',
                ]);
            }

            $document->refresh()->load('signers');
            app(DocumentSigningService::class)->submit($document);

            $minutes->update([
                'status' => BureauMinutesStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'minutes_pdf_path' => $relativePath,
                'document_id' => $document->id,
            ]);

            return $document;
        });
    }
}
