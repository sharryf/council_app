<?php

namespace App\Models;

use App\Enums\AssetAuditOutcome;
use App\Enums\AssetAuditReviewAction;
use App\Enums\AssetAuditVerifyMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'session_id', 'asset_id', 'expected_room_id', 'verified_at', 'verified_by', 'verify_method',
    'found_room_id', 'outcome', 'review_action', 'reviewed_by', 'reviewed_at', 'review_note',
])]
class AssetAuditItem extends Model
{
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'verify_method' => AssetAuditVerifyMethod::class,
            'outcome' => AssetAuditOutcome::class,
            'review_action' => AssetAuditReviewAction::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssetAuditSession::class, 'session_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function expectedRoom(): BelongsTo
    {
        return $this->belongsTo(AssetRoom::class, 'expected_room_id');
    }

    public function foundRoom(): BelongsTo
    {
        return $this->belongsTo(AssetRoom::class, 'found_room_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function needsReview(): bool
    {
        return $this->outcome !== null && $this->outcome->needsReview() && $this->review_action === null;
    }
}
