<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use App\Enums\InventorySourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable ledger line per quantity change (spec decision D1) —
 * never updated or deleted after creation. The only code that may
 * create one of these is App\Services\Inventory\StockMovementService —
 * see its own comment on why that rule matters.
 */
#[Fillable([
    'movement_no', 'movement_date', 'item_id', 'location_id', 'movement_type', 'direction',
    'quantity', 'balance_after', 'source_type', 'source_id', 'source_line_id', 'source_no',
    'issue_receipt_id', 'reversal_of_id', 'is_reversed', 'reference', 'remarks', 'performed_by',
])]
class InventoryStockMovement extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'movement_date' => 'datetime',
            'movement_type' => InventoryMovementType::class,
            'direction' => 'integer',
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'source_type' => InventorySourceType::class,
            'is_reversed' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function issueReceipt(): BelongsTo
    {
        return $this->belongsTo(InventoryIssueReceipt::class, 'issue_receipt_id');
    }
}
