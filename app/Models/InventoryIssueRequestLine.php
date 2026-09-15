<?php

namespace App\Models;

use App\Enums\InventoryIssueRequestLineStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'request_id', 'line_no', 'item_id', 'requested_qty', 'approved_qty', 'issued_qty',
    'returned_qty', 'line_status', 'available_at_request', 'line_remarks',
])]
class InventoryIssueRequestLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'requested_qty' => 'decimal:3',
            'approved_qty' => 'decimal:3',
            'issued_qty' => 'decimal:3',
            'returned_qty' => 'decimal:3',
            'line_status' => InventoryIssueRequestLineStatus::class,
            'available_at_request' => 'decimal:3',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(InventoryIssueRequest::class, 'request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
