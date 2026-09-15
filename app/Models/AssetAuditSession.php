<?php

namespace App\Models;

use App\Enums\AssetAuditScopeType;
use App\Enums\AssetAuditSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'scope_type', 'scope_building_id', 'scope_room_id', 'status',
    'started_by', 'started_at', 'closed_by', 'closed_at',
])]
class AssetAuditSession extends Model
{
    protected function casts(): array
    {
        return [
            'scope_type' => AssetAuditScopeType::class,
            'status' => AssetAuditSessionStatus::class,
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetAuditItem::class, 'session_id');
    }

    public function scopeBuilding(): BelongsTo
    {
        return $this->belongsTo(AssetBuilding::class, 'scope_building_id');
    }

    public function scopeRoom(): BelongsTo
    {
        return $this->belongsTo(AssetRoom::class, 'scope_room_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isInProgress(): bool
    {
        return $this->status === AssetAuditSessionStatus::InProgress;
    }

    public function scopeDescription(): string
    {
        return match ($this->scope_type) {
            AssetAuditScopeType::All => 'All assets',
            AssetAuditScopeType::Building => $this->scopeBuilding?->name ?? 'Building',
            AssetAuditScopeType::Room => $this->scopeRoom?->path() ?? 'Room',
        };
    }
}
