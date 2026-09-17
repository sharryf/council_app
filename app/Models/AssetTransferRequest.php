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
     * "Pending" or "Approved by Jane Doe" — who decided this request,
     * for the compact log on the asset view.
     */
    public function decisionSummary(): string
    {
        return $this->status === AssetTransferStatus::Pending
            ? 'Pending'
            : "{$this->status->getLabel()} by ".($this->decidedBy?->name ?? '—');
    }
}
