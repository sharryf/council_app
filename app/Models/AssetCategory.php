<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['parent_id', 'gl_code', 'name', 'asset_class_code', 'is_active'])]
class AssetCategory extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    public function isSubCategory(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * "Category > Sub-category (Z001)" for select options and history
     * values — human-readable, no join needed by callers that already
     * have both loaded. The asset class code (when set) is appended
     * since it's the code actually printed on labels/registers, per
     * the government asset classification the app's category tree is
     * seeded from (see AssetCategorySeeder).
     */
    public function path(): string
    {
        $path = $this->parent
            ? "{$this->parent->name} > {$this->name}"
            : $this->name;

        return $this->asset_class_code ? "{$path} ({$this->asset_class_code})" : $path;
    }
}
