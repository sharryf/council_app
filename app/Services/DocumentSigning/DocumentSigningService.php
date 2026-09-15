<?php

namespace App\Services\DocumentSigning;

use App\Enums\DocumentStatus;
use App\Enums\SignatureType;
use App\Enums\SigningMode;
use App\Enums\SignerStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Owns the document-signing state machine: who may sign, reject, or
 * void right now, computing each signature's verification hash, and
 * rolling the document to Signed (plus generating the stamped
 * certificate) once every required signer has signed.
 */
class DocumentSigningService
{
    public function signerFor(Document $document, User $user): ?DocumentSigner
    {
        return $document->signers->firstWhere('user_id', $user->id);
    }

    public function canSign(Document $document, User $user): bool
    {
        if ($document->status !== DocumentStatus::Pending) {
            return false;
        }

        $signer = $this->signerFor($document, $user);

        if (! $signer || $signer->status !== SignerStatus::Pending) {
            return false;
        }

        if ($document->signing_mode === SigningMode::Parallel) {
            return true;
        }

        return $document->signers
            ->where('order', '<', $signer->order)
            ->every(fn (DocumentSigner $earlier): bool => $earlier->status === SignerStatus::Signed);
    }

    /**
     * Any signer who hasn't yet acted may reject, regardless of whether
     * it's their turn in a sequential document — so a downstream signer
     * can flag a problem before the document ever reaches them.
     */
    public function canReject(Document $document, User $user): bool
    {
        if ($document->status !== DocumentStatus::Pending) {
            return false;
        }

        return $this->signerFor($document, $user)?->status === SignerStatus::Pending;
    }

    public function sign(Document $document, User $user, SignatureType $type, string $signatureValue): void
    {
        $signer = $this->signerFor($document, $user);

        if (! $signer || ! $this->canSign($document, $user)) {
            throw ValidationException::withMessages([
                'signature' => 'You are not able to sign this document right now.',
            ]);
        }

        DB::transaction(function () use ($document, $signer, $user, $type, $signatureValue) {
            $timestamp = now();

            $signer->update([
                'status' => SignerStatus::Signed,
                'signature_type' => $type,
                'signature_value' => $signatureValue,
                'hash' => $this->computeHash($document, $user, $timestamp),
                'signed_at' => $timestamp,
            ]);

            $document->unsetRelation('signers');
            $document->load('signers.user');

            if ($document->isFullySigned()) {
                $document->update([
                    'status' => DocumentStatus::Signed,
                    'signed_at' => now(),
                ]);

                $document->update([
                    'signed_file_path' => app(DocumentStampService::class)->generate($document),
                ]);
            }
        });
    }

    public function reject(Document $document, User $user, string $reason): void
    {
        $signer = $this->signerFor($document, $user);

        if (! $signer || ! $this->canReject($document, $user)) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'You are not able to reject this document right now.',
            ]);
        }

        DB::transaction(function () use ($document, $signer, $reason) {
            $signer->update([
                'status' => SignerStatus::Rejected,
                'rejection_reason' => $reason,
            ]);

            $document->update([
                'status' => DocumentStatus::Rejected,
                'rejection_reason' => $reason,
            ]);
        });
    }

    /**
     * Only a Pending document can be voided — a Draft is already
     * deletable outright, and a Signed document is a completed record
     * that a simple administrative cancellation shouldn't be able to
     * unwind (see DocumentResource::voidAction()'s doc comment for who
     * is additionally allowed to trigger this).
     */
    public function canVoid(Document $document): bool
    {
        return $document->status === DocumentStatus::Pending;
    }

    /**
     * An administrative cancellation, distinct from reject(): it isn't
     * attributed to any signer's own objection, so signer rows are left
     * exactly as they were (some may already be Signed) — nothing about
     * what already happened gets rewritten, the document just stops
     * being actionable from here.
     */
    public function void(Document $document, User $actor, string $reason): void
    {
        if (! $this->canVoid($document)) {
            throw ValidationException::withMessages([
                'void_reason' => 'Only a pending document can be voided.',
            ]);
        }

        $document->update([
            'status' => DocumentStatus::Voided,
            'void_reason' => $reason,
            'voided_at' => now(),
            'voided_by' => $actor->id,
        ]);
    }

    public function submit(Document $document): void
    {
        if ($document->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft documents can be submitted for signing.',
            ]);
        }

        if ($document->signers->isEmpty()) {
            throw ValidationException::withMessages([
                'signers' => 'Add at least one signer before submitting.',
            ]);
        }

        $document->update(['status' => DocumentStatus::Pending]);
    }

    private function computeHash(Document $document, User $user, DateTimeInterface $timestamp): string
    {
        $fileBytes = Storage::disk('local')->get($document->file_path);

        return hash('sha256', $fileBytes.$user->id.$timestamp->format(DateTimeInterface::ATOM));
    }
}
