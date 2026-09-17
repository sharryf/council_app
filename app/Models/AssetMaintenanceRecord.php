<?php

namespace App\Models;

use App\Enums\AssetMaintenanceApprovalStatus;
use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id', 'description', 'maintenance_date', 'cost', 'previous_status', 'closing_status',
    'closed_at', 'closed_by', 'approval_status', 'recorded_by', 'decided_by', 'decided_at', 'decision_note',
])]
class AssetMaintenanceRecord extends Model
{
    protected function casts(): array
    {
        return [
            'maintenance_date' => 'date',
            'cost' => 'decimal:2',
            'previous_status' => AssetStatus::class,
            'closing_status' => AssetStatus::class,
            'closed_at' => 'datetime',
            'approval_status' => AssetMaintenanceApprovalStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * "Pending" or "Approved by Jane Doe" — who decided this record, for
     * the compact log on the asset view.
     */
    public function decisionSummary(): string
    {
        return $this->approval_status === AssetMaintenanceApprovalStatus::Pending
            ? 'Pending'
            : "{$this->approval_status->getLabel()} by ".($this->decidedBy?->name ?? '—');
    }
}
