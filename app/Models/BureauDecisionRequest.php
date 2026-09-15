<?php

namespace App\Models;

use App\Enums\BureauDecisionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'minutes_id', 'agenda_item_id', 'proposed_by', 'text', 'sort_order', 'status', 'decided_at', 'created_by',
])]
class BureauDecisionRequest extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => BureauDecisionStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function minutes(): BelongsTo
    {
        return $this->belongsTo(BureauMeetingMinutes::class, 'minutes_id');
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(BureauAgendaItem::class, 'agenda_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function votes(): HasMany
    {
        // Explicit FK — Laravel's default guess for a HasMany is based
        // on this model's own name (bureau_decision_request_id), but
        // the migration names the column decision_request_id.
        return $this->hasMany(BureauDecisionVote::class, 'decision_request_id');
    }

    public function isPending(): bool
    {
        return $this->status === BureauDecisionStatus::Pending;
    }
}
