<?php

namespace App\Models;

use App\Enums\AssetAcquisitionType;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'asset_tag', 'main_inventory_no', 'main_sequence', 'public_token', 'name', 'category_id',
    'brand', 'model', 'serial_number', 'description', 'purchase_date', 'purchase_price', 'vendor',
    'fund_code', 'po_number', 'voucher_number', 'donation_reference_no',
    'asset_type', 'room_id', 'status', 'photo_attachment_id', 'created_by',
])]
class Asset extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
            'status' => AssetStatus::class,
            'asset_type' => AssetAcquisitionType::class,
        ];
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
}
