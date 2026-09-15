<?php

namespace App\Models;

use App\Enums\InventoryReturnCondition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['return_id', 'line_no', 'item_id', 'issue_line_id', 'quantity', 'condition', 'line_remarks'])]
class InventoryStockReturnLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'condition' => InventoryReturnCondition::class,
        ];
    }

    public function return(): BelongsTo
    {
        return $this->belongsTo(InventoryStockReturn::class, 'return_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function issueLine(): BelongsTo
    {
        return $this->belongsTo(InventoryIssueRequestLine::class, 'issue_line_id');
    }
}
