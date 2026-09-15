<?php

namespace App\Models;

use App\Enums\BureauVoteChoice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['decision_request_id', 'user_id', 'vote'])]
class BureauDecisionVote extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'vote' => BureauVoteChoice::class,
        ];
    }

    public function decisionRequest(): BelongsTo
    {
        return $this->belongsTo(BureauDecisionRequest::class, 'decision_request_id');
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
