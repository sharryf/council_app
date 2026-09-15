<?php

namespace App\Mail\Bureau;

use App\Models\BureauMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Sent to every required attendee once the Council President approves
 * a meeting (see MeetingResource::approveAction()) — carries the
 * generated agenda PDF (App\Services\Bureau\MeetingAgendaPdfService).
 */
class MeetingScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BureauMeeting $meeting) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('bureau.mail.meeting_scheduled.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'bureau.mail.meeting-scheduled',
            with: ['meeting' => $this->meeting],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->meeting->hasAgendaPdf()) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $this->meeting->agenda_pdf_path)
                ->as(Str::slug($this->meeting->name ?: 'meeting').'-agenda.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
