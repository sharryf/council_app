<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'minutes_id', 'agenda_item_id', 'speaker_id', 'bullet_points',
    'drafted_text', 'sort_order', 'created_by', 'edited_by', 'edited_at',
])]
class BureauMinutesComment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
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

    public function speaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'speaker_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
