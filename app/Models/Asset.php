<?php

namespace App\Models;

use App\Enums\AssetAcquisitionType;
use App\Enums\AssetAuditSessionStatus;
use App\Enums\AssetDeleteRequestStatus;
use App\Enums\AssetEditRequestStatus;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'asset_tag', 'main_inventory_no', 'main_sequence', 'public_token', 'name', 'category_id',
    'brand', 'model', 'serial_number', 'description', 'purchase_date', 'purchase_price', 'vendor',
    'fund_code', 'po_number', 'voucher_number', 'donation_reference_no',
    'asset_type', 'room_id', 'status', 'lifecycle_status', 'photo_attachment_id', 'created_by',
])]
class Asset extends Model
{
    use SoftDeletes;

    /**
     * Every directly-editable field that goes through the generic
     * edit-request/approval flow once an asset is Posted — human labels
     * match asset_history's field_name column (implementation plan
     * section 3.7). room_id and photo are deliberately excluded: a room
     * move always goes through the separate Transfer-request flow, and
     * a posted asset's photo isn't editable through any flow yet.
     * Shared by EditAsset (applies directly while Draft) and
     * AssetEditRequestResource::approveAction() (applies on approval)
     * so both write identical history rows.
     */
    public const EDITABLE_FIELD_LABELS = [
        'name' => 'Name',
        'category_id' => 'Category',
        'brand' => 'Brand',
        'model' => 'Model',
        'serial_number' => 'Serial number',
        'description' => 'Description',
        'purchase_date' => 'Purchase date',
        'purchase_price' => 'Purchase price',
        'vendor' => 'Vendor',
        'status' => 'Status',
        'fund_code' => 'Fund code',
        'po_number' => 'PO number',
        'voucher_number' => 'Voucher number',
        'donation_reference_no' => 'Donation document ref. no.',
        'asset_type' => 'Asset type',
    ];

    /**
     * Writes one asset_history row per field that actually changed
     * between $before (an attribute snapshot taken before the update,
     * via ->only(array_keys(EDITABLE_FIELD_LABELS))) and $this's current
     * state. category_id renders as its path (e.g. "Furniture > Chairs"),
     * not a bare id, so the timeline reads without a join to a possibly-
     * deleted category.
     *
     * @param  array<string, mixed>  $before
     */
    public function recordFieldChanges(array $before): void
    {
        foreach (self::EDITABLE_FIELD_LABELS as $field => $label) {
            $old = $before[$field] ?? null;
            $new = $this->{$field};

            if ($field === 'category_id') {
                if (self::scalarize($old) === self::scalarize($new)) {
                    continue;
                }

                $old = $old ? AssetCategory::find($old)?->path() : null;
                $new = $this->category?->path();
            } elseif (self::scalarize($old) === self::scalarize($new)) {
                continue;
            }

            AssetHistory::recordFieldChange($this->id, $label, $old !== null ? self::scalarize($old) : null, $new !== null ? self::scalarize($new) : null);
        }
    }

    /**
     * A backed enum can't be cast with (string) directly (it's an
     * object, not a Stringable) — every EDITABLE_FIELD_LABELS
     * comparison/history-write needs a plain scalar regardless of
     * whether the value came in cast (an enum, a Carbon date) or raw
     * (a form's submitted string), so this is the one place that
     * normalizes either shape.
     */
    public static function scalarize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
            'status' => AssetStatus::class,
            'asset_type' => AssetAcquisitionType::class,
            'lifecycle_status' => AssetLifecycleStatus::class,
        ];
    }

    public function isDraft(): bool
    {
        return $this->lifecycle_status === AssetLifecycleStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->lifecycle_status === AssetLifecycleStatus::Posted;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(AssetRoom::class);
    }

    public function photoAttachment(): BelongsTo
    {
        return $this->belongsTo(AssetAttachment::class, 'photo_attachment_id');
    }

    /**
     * Swaps this asset's photo for a newly uploaded one — deletes the
     * old AssetAttachment (if any) and creates a new one from the given
     * private-disk path. Shared by a Draft's direct edit (applies right
     * away) and an approved edit request on a Posted asset (applies at
     * approval time) — either path logs the same 'photo_replaced' event.
     */
    public function replacePhoto(string $path): void
    {
        $this->photoAttachment?->delete();

        $attachment = AssetAttachment::create([
            'asset_id' => $this->id,
            'kind' => 'photo',
            'file_path' => $path,
            'file_name' => basename($path),
            'mime_type' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream',
            'file_size' => Storage::disk('local')->size($path),
            'uploaded_by' => auth()->id(),
        ]);

        $this->update(['photo_attachment_id' => $attachment->id]);

        AssetHistory::record($this->id, 'photo_replaced');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssetAttachment::class);
    }

    public function documents(): HasMany
    {
        return $this->attachments()->where('kind', 'document');
    }

    public function history(): HasMany
    {
        return $this->hasMany(AssetHistory::class)->latest('created_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transferRequests(): HasMany
    {
        return $this->hasMany(AssetTransferRequest::class)->latest('requested_at');
    }

    public function pendingTransferRequest(): ?AssetTransferRequest
    {
        return $this->transferRequests->firstWhere('status', AssetTransferStatus::Pending);
    }

    public function hasPendingTransfer(): bool
    {
        return $this->pendingTransferRequest() !== null;
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(AssetMaintenanceRecord::class)->latest('maintenance_date');
    }

    public function openMaintenanceRecord(): ?AssetMaintenanceRecord
    {
        return $this->maintenanceRecords->firstWhere('closed_at', null);
    }

    public function editRequests(): HasMany
    {
        return $this->hasMany(AssetEditRequest::class)->latest('requested_at');
    }

    public function auditItems(): HasMany
    {
        return $this->hasMany(AssetAuditItem::class);
    }

    /**
     * Deleting an asset that's still on an in-progress audit's checklist
     * would leave that audit item pointing at nothing — the workstation
     * page (ViewAssetAuditSession) loads $item->asset via a plain
     * belongsTo, which SoftDeletes silently excludes once trashed, so
     * every render/verify/review path for that row would start hitting
     * a null asset. Blocking the delete here is cheaper than teaching
     * every one of those paths to cope with a vanished asset.
     */
    public function hasActiveAuditItem(): bool
    {
        return $this->auditItems()
            ->whereHas('session', fn ($q) => $q->where('status', AssetAuditSessionStatus::InProgress))
            ->exists();
    }

    public function pendingEditRequest(): ?AssetEditRequest
    {
        return $this->editRequests->firstWhere('status', AssetEditRequestStatus::Pending);
    }

    public function hasPendingEditRequest(): bool
    {
        return $this->pendingEditRequest() !== null;
    }

    public function deleteRequests(): HasMany
    {
        return $this->hasMany(AssetDeleteRequest::class)->latest('requested_at');
    }

    public function pendingDeleteRequest(): ?AssetDeleteRequest
    {
        return $this->deleteRequests->firstWhere('status', AssetDeleteRequestStatus::Pending);
    }

    public function hasPendingDeleteRequest(): bool
    {
        return $this->pendingDeleteRequest() !== null;
    }
}
