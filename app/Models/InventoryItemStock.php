<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['item_id', 'location_id', 'on_hand', 'reserved', 'last_movement_at', 'last_counted_at'])]
class InventoryItemStock extends Model
{
    protected $table = 'inventory_item_stock';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'on_hand' => 'decimal:3',
            'reserved' => 'decimal:3',
            'last_movement_at' => 'datetime',
            'last_counted_at' => 'datetime',
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

    /**
     * on_hand − reserved — see spec 7.1. Never validate against
     * on_hand alone; this is the figure shown to requesters and
     * checked at approval time.
     */
    public function getAvailableAttribute(): string
    {
        return bcsub((string) $this->on_hand, (string) $this->reserved, 3);
    }
}
