<?php

namespace App\Models;

use App\Enums\SignatureType;
use App\Enums\SignerStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'document_id', 'user_id', 'order', 'role_label', 'status',
    'signature_type', 'signature_value', 'hash', 'signed_at', 'rejection_reason',
])]
class DocumentSigner extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SignerStatus::class,
            'signature_type' => SignatureType::class,
            'signed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Where this signer's signature is composited on the document — can
     * be zero (e.g. a document created outside the wizard, or in a
     * test — DocumentStampService then leaves this signer out of the
     * output entirely), one, or several (e.g. initials on every page
     * plus a full signature on the last). Same one-to-many shape as
     * DocumentStamp is to Document.
     */
    public function placements(): HasMany
    {
        return $this->hasMany(DocumentSignerPlacement::class);
    }

    /**
     * Absolute filesystem path to a drawn or reused-saved signature's
     * PNG on the `local` disk. Only meaningful when signature_type is
     * image-based (Drawn or Saved) — a Typed signature has no file.
     */
    public function signatureDiskPath(): ?string
    {
        if (! $this->signature_type?->isImageBased() || blank($this->signature_value)) {
            return null;
        }

        return Storage::disk('local')->path($this->signature_value);
    }
}
