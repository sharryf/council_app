<?php

namespace App\Models;

use App\Enums\InventoryReturnStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'return_no', 'return_date', 'issue_request_id', 'returned_by', 'location_id', 'reason',
    'status', 'received_by', 'posted_at',
])]
class InventoryStockReturn extends Model
{
    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'status' => InventoryReturnStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function issueRequest(): BelongsTo
    {
        return $this->belongsTo(InventoryIssueRequest::class, 'issue_request_id');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryStockReturnLine::class, 'return_id');
    }
}
