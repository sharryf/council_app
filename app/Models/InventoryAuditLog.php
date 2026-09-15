<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['entity_type', 'entity_id', 'action', 'old_values', 'new_values', 'changed_by', 'changed_at', 'ip_address', 'user_agent'])]
class InventoryAuditLog extends Model
{
    protected $table = 'inventory_audit_log';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Shared writer for every BR-28 ("every state transition writes to
     * audit_log") call site in the Inventory module, filling the
     * actor/request fields so callers only state what happened.
     *
     * Issue Requests deliberately don't call this — they already have
     * their own approvalActions() history table covering SUBMITTED/
     * APPROVED/REJECTED/RETURNED_FOR_EDIT/CANCELLED/ISSUED, and writing
     * the same six events into two tables would add no traceability.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function write(string $entityType, int $entityId, string $action, ?array $oldValues = null, ?array $newValues = null): void
    {
        static::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
