<?php

namespace App\Models;

use App\Enums\InventoryAdjustmentStatus;
use App\Enums\InventoryAdjustmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'adjustment_no', 'adjustment_date', 'location_id', 'adjustment_type', 'reason', 'status',
    'approved_by', 'approved_at', 'posted_by', 'posted_at', 'attachment_path', 'created_by',
])]
class InventoryStockAdjustment extends Model
{
    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'adjustment_type' => InventoryAdjustmentType::class,
            'status' => InventoryAdjustmentStatus::class,
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryStockAdjustmentLine::class, 'adjustment_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
