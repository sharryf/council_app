<?php

namespace App\Models;

use App\Enums\AssetEditRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id', 'requested_by', 'requested_at', 'reason', 'proposed_changes',
    'status', 'reviewed_by', 'reviewed_at', 'review_note',
])]
class AssetEditRequest extends Model
{
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'proposed_changes' => 'array',
            'status' => AssetEditRequestStatus::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * "Name, Brand, Purchase price" — which fields this request
     * touches, for a compact table column. Values themselves aren't
     * shown here (category_id/status are raw ids/enum values at this
     * point, not yet resolved to display text) — the full before/after
     * only renders once, on the asset's own history timeline after a
     * decision.
     */
    public function fieldsSummary(): string
    {
        return collect($this->proposed_changes)
            ->keys()
            ->map(fn (string $field): string => Asset::EDITABLE_FIELD_LABELS[$field] ?? ($field === 'photo' ? 'Photo' : $field))
            ->implode(', ');
    }
}
