<?php

namespace App\Models;

use App\Enums\InventoryRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'role'])]
class InventoryUserRole extends Model
{
    protected function casts(): array
    {
        return [
            'role' => InventoryRole::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
