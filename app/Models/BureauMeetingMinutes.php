<?php

namespace App\Models;

use App\Enums\BureauMinutesAttendanceGroup;
use App\Enums\BureauMinutesStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'meeting_id', 'status', 'chaired_by', 'started_at', 'break_started_at', 'break_ended_at', 'ended_at',
    'introduction', 'closing_notes', 'audio_path', 'started_by', 'reviewed_by', 'reviewed_at',
    'minutes_pdf_path', 'document_id',
])]
class BureauMeetingMinutes extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => BureauMinutesStatus::class,
            'started_at' => 'datetime',
            'break_started_at' => 'datetime',
            'break_ended_at' => 'datetime',
            'ended_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(BureauMeeting::class, 'meeting_id');
    }

    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chaired_by');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Council roll call — every President/Councillor system-wide gets a
     * row the moment minutes start (see RecordMinutes::startMinutes()),
     * marked Present/Absent/On Leave with an attendance time. Split from
     * secretariatAttendance() by the explicit role_group pivot column
     * rather than the user's current roles, so it stays correct even if
     * roles change after the meeting.
     */
    public function councilAttendance(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bureau_minutes_attendance', 'minutes_id', 'user_id')
            ->wherePivot('role_group', BureauMinutesAttendanceGroup::Council->value)
            ->withPivot(['role_group', 'status', 'attended_at']);
    }

    /**
     * Secretariat roll call (Participant + Bureau Admin roster) — a
     * simple Present/Absent checkbox in the UI, no time recorded.
     */
    public function secretariatAttendance(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bureau_minutes_attendance', 'minutes_id', 'user_id')
            ->wherePivot('role_group', BureauMinutesAttendanceGroup::Secretariat->value)
            ->withPivot(['role_group', 'status', 'attended_at']);
    }

    /**
     * Members of the public who attended to listen — free-text
     * name+address, never a User. See BureauMinutesListener.
     */
    public function listeners(): HasMany
    {
        return $this->hasMany(BureauMinutesListener::class, 'minutes_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BureauMinutesComment::class, 'minutes_id')->orderBy('sort_order');
    }

    public function decisionRequests(): HasMany
    {
        return $this->hasMany(BureauDecisionRequest::class, 'minutes_id')->orderBy('sort_order');
    }

    public function hasAudio(): bool
    {
        return filled($this->audio_path);
    }

    public function isEditableByAttendees(): bool
    {
        return $this->status === BureauMinutesStatus::Review;
    }

    /**
     * Pre-filled (but freely editable) opening boilerplate the council
     * always uses — same "static template, then edit" spirit as
     * BureauMeeting::DEFAULT_PLACE, just interpolating this specific
     * meeting's term/number/type the way BureauMeeting::buildName()
     * does.
     */
    public static function defaultIntroduction(BureauMeeting $meeting): string
    {
        return "بسم ﷲ الرحمن الرحيم، الحمد لله والصلاة والسلام على رسول ﷲ وعلى ٱله وصحبه ومن والاہ\n"
            ."ދޮންފަނު ކައުންސިލުގެ {$meeting->term_number} ވަނަ ދައުރުގެ {$meeting->meeting_number} ވަނަ {$meeting->type_label}ގައި ބައިވެރިވާ މެންބަރުންނަށް މަރްޙަބާ ދަންނަވަން. ބައްދަލުވުން ފެށުނީ. "
            .'މިބައްދަލުވުމުގެ އެޖެންޑާ މެންބަރުންނަށް އެރުވިފައިވާނެ.';
    }

    /**
     * Pre-filled closing boilerplate — the "ބައްދަލުވުން ނިމުނީ ⟨ގަޑި⟩
     * ގައެވެ" clause is deliberately left for the Bureau Admin to type
     * in themselves, since the actual end time isn't known until the
     * meeting is over.
     */
    public static function defaultClosingNotes(BureauMeeting $meeting): string
    {
        return "މިހިސާބުން {$meeting->meeting_number} ވަނަ ބައްދަލުވުމުގައި ހުށަހެޅުނު މައްސަލަތަކަށް ގޮތް ނިންމުން ނިމުނީ ކަމަށާއި، އަދި މިނިންމުންތައް ތަންފީޛު ކޮށްދެއްވުމަށް ސެކްރެޓަރީ ޖެނެރަލްގެ އަރިހުން ވަރަށް އަދަބުވެރިމާއެކު އެދޭ ކަމަށް ރިޔާސަތުން ވިދާޅުވި. ވަލްޙަމްދު ﷲ ރައްބިލްޢާލަމީން. އަދި މިޖަލްސާ މިހިސާބުން ނިމުނީކަމަށް ރިޔާސަތުން އިޢްލާން ކުރެއްވި. ބައްދަލުވުން ނިމުނީ";
    }
}
