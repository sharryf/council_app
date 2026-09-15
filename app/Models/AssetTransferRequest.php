<?php

namespace App\Models;

use App\Enums\AssetTransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id', 'from_room_id', 'to_room_id', 'reason', 'status',
    'requested_by', 'requested_at', 'decided_by', 'decided_at', 'decision_note',
])]
class AssetTransferRequest extends Model
{
    protected function casts(): array
    {
        return [
            'status' => AssetTransferStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(AssetRoom::class, 'from_room_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(AssetRoom::class, 'to_room_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * A user holding both Admin and Manager may approve their own
     * request — allowed, but always shown plainly (implementation plan
     * section 4, "Self-approval"), never hidden or blocked.
     */
    public function isSelfApproved(): bool
    {
        return $this->decided_by !== null && $this->decided_by === $this->requested_by;
    }
}
