<?php

namespace App\Models;

use App\Enums\DocumentSigningRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'role'])]
class DocumentSigningUserRole extends Model
{
    protected function casts(): array
    {
        return [
            'role' => DocumentSigningRole::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
