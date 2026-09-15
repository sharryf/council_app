<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'identification_no', 'phone', 'department', 'notes', 'is_active'])]
class InventoryRecipient extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function issueRequests(): HasMany
    {
        return $this->hasMany(InventoryIssueRequest::class, 'recipient_id');
    }
}
