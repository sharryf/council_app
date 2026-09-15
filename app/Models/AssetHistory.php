<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only timeline for one asset — never updated or deleted. Unlike
 * InventoryAuditLog (one JSON-diff row per mutation), this writes one
 * row per changed field so the on-screen timeline renders directly
 * without parsing, per the implementation plan section 3.7. Every write
 * path in the Assets module must go through write() so no mutation can
 * skip logging.
 */
#[Fillable(['asset_id', 'event_type', 'field_name', 'old_value', 'new_value', 'note', 'related_type', 'related_id', 'performed_by', 'created_at'])]
class AssetHistory extends Model
{
    protected $table = 'asset_history';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * One event with no associated field (created, status_changed as a
     * single note, transfer_requested, etc.).
     */
    public static function record(
        int $assetId,
        string $eventType,
        ?string $note = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): void {
        static::create([
            'asset_id' => $assetId,
            'event_type' => $eventType,
            'note' => $note,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'performed_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    /**
     * One row per changed field — call once per field, per the spec's
     * "field_changed" event guidance. Values must already be
     * human-readable strings (e.g. a room's name, not its id) so the
     * timeline never needs a join to a possibly-deleted record.
     */
    public static function recordFieldChange(
        int $assetId,
        string $fieldName,
        ?string $oldValue,
        ?string $newValue,
        string $eventType = 'field_changed',
    ): void {
        static::create([
            'asset_id' => $assetId,
            'event_type' => $eventType,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'performed_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}
