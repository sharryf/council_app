<?php

namespace App\Filament\Resources\DocumentSigning\Documents\Widgets;

use App\Enums\DocumentSigningRole;
use App\Enums\DocumentStatus;
use App\Enums\SignerStatus;
use App\Models\Document;
use Filament\Widgets\Widget;

/**
 * This module's "dashboard" card — the Documents list is the module's
 * home page, so this sits at its top rather than on a separate
 * dashboard route. Shown to anyone who can sign at all (see
 * App\Enums\DocumentSigningRole::Signee); "pending" mirrors
 * DocumentsTable's own "Assigned to me (pending)" filter — a listed
 * signer whose row is still Pending, not the stricter "genuinely your
 * turn right now in a sequential document" (see
 * DocumentSigningService::canSign()) — so the count matches what that
 * filter shows when clicked.
 */
class PendingSignaturesWidget extends Widget
{
    protected string $view = 'filament.widgets.pending-signatures';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->hasDocumentSigningRole(DocumentSigningRole::Signee) ?? false;
    }

    public function getPendingCount(): int
    {
        return Document::query()
            ->where('status', DocumentStatus::Pending)
            ->whereHas('signers', fn ($query) => $query
                ->where('user_id', auth()->id())
                ->where('status', SignerStatus::Pending))
            ->count();
    }

    /**
     * This widget and the Documents table below it are separate Livewire
     * components (Widget extends its own Livewire\Component), so the
     * card can't call the table's filter state directly — it dispatches
     * a browser event that ListDocuments listens for instead. The card
     * is always wired up to be clickable (see the blade view's comment
     * on why the attribute itself can't be conditional) so this no-ops
     * when there's nothing to filter to.
     */
    public function filterToPending(): void
    {
        if ($this->getPendingCount() === 0) {
            return;
        }

        $this->dispatch('apply-pending-documents-filter');
    }
}
