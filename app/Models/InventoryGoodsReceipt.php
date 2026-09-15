<?php

namespace App\Models;

use App\Enums\InventoryGoodsReceiptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'grn_no', 'receipt_date', 'location_id', 'supplier_id', 'reference_type', 'invoice_no',
    'invoice_date', 'po_no', 'delivery_note_no', 'status', 'remarks', 'posted_by', 'posted_at',
    'reversed_by', 'reversed_at', 'reversal_reason', 'created_by', 'updated_by',
])]
class InventoryGoodsReceipt extends Model
{
    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'invoice_date' => 'date',
            'status' => InventoryGoodsReceiptStatus::class,
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(InventorySupplier::class, 'supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryGoodsReceiptLine::class, 'grn_id');
    }

    /**
     * Attachments live in the shared inventory_attachments table (spec
     * 6.9), keyed by a plain string entity_type ('GRN') rather than
     * Eloquent's class-name-based morph convention — a bare hasMany
     * plus an extra where() avoids needing a morph-map registration
     * just for this one relation.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(InventoryAttachment::class, 'entity_id')->where('entity_type', 'GRN');
    }
}
