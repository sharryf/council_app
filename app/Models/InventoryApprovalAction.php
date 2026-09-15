<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_id', 'action', 'action_by', 'action_at', 'remarks', 'level', 'snapshot'])]
class InventoryApprovalAction extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'action_at' => 'datetime',
            'level' => 'integer',
            'snapshot' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(InventoryIssueRequest::class, 'request_id');
    }

    public function actionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by');
    }
}
