<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member of the public who attended to listen — not a User (they
 * don't log in), just a name + address the Bureau Admin types in
 * during roll call. See RecordMinutes::addListener().
 */
#[Fillable(['minutes_id', 'name', 'address'])]
class BureauMinutesListener extends Model
{
    use HasFactory;

    public function minutes(): BelongsTo
    {
        return $this->belongsTo(BureauMeetingMinutes::class, 'minutes_id');
    }
}
