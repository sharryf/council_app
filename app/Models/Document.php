<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\SignerStatus;
use App\Enums\SigningMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'title', 'file_path', 'file_original_name', 'uploaded_by',
    'signing_mode', 'status', 'rejection_reason', 'signed_file_path', 'signed_at',
    'void_reason', 'voided_at', 'voided_by',
])]
class Document extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'signing_mode' => SigningMode::class,
            'status' => DocumentStatus::class,
            'signed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Deleting a document row (the ordinary Draft-only delete, or the
     * bulk age-based cleanup — see DocumentSigningCleanup) must not
     * leave its files behind on disk. Hooked here rather than in each
     * action, so every deletion path is covered — signer rows are
     * handled separately by the DB's own cascadeOnDelete, but they
     * don't own any files of their own (a signature image lives under
     * this same documents/{id}/ folder, not attached to the signer
     * row's own lifecycle).
     */
    protected static function booted(): void
    {
        static::deleting(function (Document $document): void {
            $disk = Storage::disk('local');
            $disk->delete($document->file_path);
            $disk->deleteDirectory("documents/{$document->id}");
        });
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(DocumentSigner::class)->orderBy('order');
    }

    public function stamps(): HasMany
    {
        return $this->hasMany(DocumentStamp::class);
    }

    /**
     * Title is optional in the upload wizard (see CreateDocument) — the
     * original filename stands in anywhere a title is displayed.
     */
    public function displayTitle(): string
    {
        return $this->title ?: $this->file_original_name;
    }

    public function isFullySigned(): bool
    {
        return $this->signers->isNotEmpty()
            && $this->signers->every(fn (DocumentSigner $signer): bool => $signer->status === SignerStatus::Signed);
    }

    public function signedCount(): int
    {
        return $this->signers->where('status', SignerStatus::Signed)->count();
    }

    public function totalSignersCount(): int
    {
        return $this->signers->count();
    }
}
