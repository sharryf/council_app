<?php

namespace App\Models;

use App\Enums\BureauAgendaItemKind;
use App\Enums\BureauAgendaStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'details', 'attachment_path', 'attachment_original_name', 'kind', 'related_meeting_id',
    'status', 'rejection_reason', 'created_by', 'reviewed_by', 'reviewed_at', 'meeting_id',
])]
class BureauAgendaItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => BureauAgendaStatus::class,
            'kind' => BureauAgendaItemKind::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(BureauMeeting::class, 'meeting_id');
    }

    /**
     * Only set for MinutesPassing items — which past meeting's minutes
     * this procedural item is asking the council to pass (see
     * App\Enums\BureauAgendaItemKind).
     */
    public function relatedMeeting(): BelongsTo
    {
        return $this->belongsTo(BureauMeeting::class, 'related_meeting_id');
    }

    /**
     * Approved items not yet claimed by a meeting — what a new meeting
     * auto-attaches at creation time (see CreateMeeting).
     */
    public function scopeAvailableForMeeting(Builder $query): Builder
    {
        return $query->where('status', BureauAgendaStatus::Approved)->whereNull('meeting_id');
    }

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    /**
     * A short heading for contexts that need one line — the meeting
     * agenda PDF, the minutes PDF, and the live minutes recording page
     * — now that `details` is the item's only content field (see the
     * "restructure_bureau_agenda_items_details_and_status" migration
     * for why the old separate `title` field was dropped).
     */
    public function shortLabel(): string
    {
        return Str::limit($this->details, 60);
    }
}
