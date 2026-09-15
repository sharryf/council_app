<?php

namespace App\Models;

use App\Enums\ModuleAccessLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row = one user's Editor/Approver level in one module. Absence of
 * a row for a given (user, module) pair means Viewer — see
 * User::roleFor(), the only place this should normally be read through.
 */
#[Fillable(['user_id', 'module', 'level'])]
class UserModuleLevel extends Model
{
    protected function casts(): array
    {
        return [
            'level' => ModuleAccessLevel::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
