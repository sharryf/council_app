<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

    /**
     * One human-readable line per row, for the compact log on the asset
     * view — e.g. "Status: In Use → Damaged" or "Location change
     * approved". Values are already display-ready strings (see
     * recordFieldChange's docblock), so this never needs to resolve
     * anything further.
     */
    public function summary(): string
    {
        $label = match ($this->event_type) {
            'field_changed' => trim("{$this->field_name}: ".($this->old_value ?? '—').' → '.($this->new_value ?? '—')),
            'created' => 'Created',
            'posted' => 'Posted',
            'transfer_requested' => 'Location change requested',
            'transfer_approved' => 'Location change approved',
            'transfer_rejected' => 'Location change rejected',
            'transfer_cancelled' => 'Location change cancelled',
            'edit_requested' => 'Edit requested',
            'edit_approved' => 'Edit approved',
            'edit_rejected' => 'Edit rejected',
            'delete_requested' => 'Deletion requested',
            'delete_approved' => 'Deletion approved',
            'delete_rejected' => 'Deletion rejected',
            'maintenance_logged' => 'Maintenance logged',
            'maintenance_approved' => 'Maintenance approved',
            'maintenance_rejected' => 'Maintenance rejected',
            'maintenance_closed' => 'Maintenance closed',
            'photo_replaced' => 'Photo replaced',
            'attachment_added' => 'Document added',
            'audit_verified' => 'Verified in audit',
            'audit_verification_undone' => 'Verification undone in audit',
            'audit_flagged_missing' => 'Flagged missing in audit',
            'audit_reviewed' => 'Reviewed in audit',
            default => Str::headline($this->event_type),
        };

        $note = $this->event_type === 'field_changed' ? null : $this->note;

        return implode(' — ', array_filter([$label, $note]));
    }
}
