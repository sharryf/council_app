<?php

namespace App\Models;

use App\Enums\InventoryIssueRequestStatus;
use App\Enums\InventoryPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'request_no', 'request_date', 'requested_by', 'location_id', 'recipient_id', 'purpose', 'required_by_date',
    'priority', 'delivery_to', 'status', 'submitted_at', 'approver_id', 'approved_at',
    'approval_remarks', 'rejected_at', 'rejection_reason', 'issued_by', 'issued_at',
    'received_by_name', 'receiver_signature_path', 'cancelled_at', 'cancelled_by',
    'cancellation_reason', 'total_lines', 'total_qty', 'remarks',
])]
class InventoryIssueRequest extends Model
{
    protected function casts(): array
    {
        return [
            'request_date' => 'datetime',
            'required_by_date' => 'date',
            'priority' => InventoryPriority::class,
            'status' => InventoryIssueRequestStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_lines' => 'integer',
            'total_qty' => 'decimal:3',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(InventoryRecipient::class, 'recipient_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryIssueRequestLine::class, 'request_id');
    }

    public function approvalActions(): HasMany
    {
        return $this->hasMany(InventoryApprovalAction::class, 'request_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(InventoryIssueReceipt::class, 'request_id')->orderBy('issued_at');
    }

    /**
     * Same plain, string-keyed "polymorphism" as
     * InventoryGoodsReceipt::attachments() — inventory_attachments is
     * keyed by entity_type/entity_id, not Eloquent's morph convention.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(InventoryAttachment::class, 'entity_id')->where('entity_type', 'ISSUE_REQUEST');
    }
}
