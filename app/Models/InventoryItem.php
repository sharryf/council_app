<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'description', 'category_id', 'uom_id', 'brand', 'model_spec',
    'bin_location', 'image_path', 'reorder_level', 'reorder_qty', 'max_level',
    'lead_time_days', 'default_supplier_id', 'last_received_date', 'is_stock_tracked',
    'is_active', 'notes', 'created_by', 'updated_by',
])]
class InventoryItem extends Model
{
    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:3',
            'reorder_qty' => 'decimal:3',
            'max_level' => 'decimal:3',
            'lead_time_days' => 'integer',
            'last_received_date' => 'date',
            'is_stock_tracked' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryItemCategory::class, 'category_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(InventoryUnitOfMeasure::class, 'uom_id');
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(InventorySupplier::class, 'default_supplier_id');
    }

    public function stock(): HasMany
    {
        return $this->hasMany(InventoryItemStock::class, 'item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'item_id');
    }
}
