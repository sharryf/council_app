<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One immutable row per issue batch (see the creating migration's
 * comment) — never updated after creation, the same rule
 * InventoryStockMovement follows.
 */
#[Fillable(['request_id', 'issued_by', 'issued_at', 'received_by_name', 'receiver_signature_path'])]
class InventoryIssueReceipt extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(InventoryIssueRequest::class, 'request_id');
    }

    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'issue_receipt_id');
    }
}
