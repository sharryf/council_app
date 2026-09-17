<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'is_active'])]
class AssetFundCode extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * "J-GOM — Government of Maldives" for select options; falls back
     * to the bare code when no descriptive name is set.
     */
    public function label(): string
    {
        return $this->name ? "{$this->code} — {$this->name}" : $this->code;
    }
}
