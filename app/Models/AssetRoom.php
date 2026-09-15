<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['building_id', 'name', 'is_active'])]
class AssetRoom extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(AssetBuilding::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'room_id');
    }

    /**
     * "Building > Room" for select options and history values.
     */
    public function path(): string
    {
        return "{$this->building->name} > {$this->name}";
    }
}
