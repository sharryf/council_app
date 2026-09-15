<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['current_term_number', 'next_public_meeting_number', 'next_private_meeting_number', 'pdf_header_text', 'pdf_footer_text'])]
class BureauSettings extends Model
{
    /**
     * Always row id=1 — one settings row for the whole module, same
     * reasoning as any singleton-settings table.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'current_term_number' => 1,
            'next_public_meeting_number' => 1,
            'next_private_meeting_number' => 1,
        ]);
    }

    /**
     * Public and emergency meetings are numbered in separate sequences
     * — see MeetingForm's type Select and BureauMeeting::TYPES.
     */
    public function nextMeetingNumberFor(?string $type): int
    {
        return $type === 'private' ? $this->next_private_meeting_number : $this->next_public_meeting_number;
    }

    public function incrementMeetingNumberFor(?string $type): void
    {
        $this->increment($type === 'private' ? 'next_private_meeting_number' : 'next_public_meeting_number');
    }
}
