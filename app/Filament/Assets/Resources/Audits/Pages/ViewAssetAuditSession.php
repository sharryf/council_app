<?php

namespace App\Filament\Assets\Resources\Audits\Pages;

use App\Enums\AssetAuditOutcome;
use App\Enums\AssetAuditReviewAction;
use App\Enums\AssetAuditScopeType;
use App\Enums\AssetAuditSessionStatus;
use App\Enums\AssetAuditVerifyMethod;
use App\Enums\AssetStatus;
use App\Filament\Assets\Resources\Audits\AssetAuditSessionResource;
use App\Models\Asset;
use App\Models\AssetAuditItem;
use App\Models\AssetAuditSession;
use App\Models\AssetHistory;
use App\Services\Assets\AssetLock;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The live audit workstation (spec section 11 / 6.8) — verify assets
 * either by scanning a printed label's QR code (native BarcodeDetector
 * API where the browser supports it — see the Blade view; no external
 * JS library, matching this app's existing camera/mic-feature
 * convention of sticking to built-in browser APIs, e.g.
 * record-minutes.blade.php's MediaRecorder usage) or by ticking the
 * manual checklist, then close the session (computing every item's
 * outcome) and work through the review queue.
 *
 * Not built: true offline queueing (spec's "queue verifications
 * locally and sync when connectivity returns") — this app has no
 * offline/service-worker infrastructure anywhere. verifyCode() instead
 * gives an explicit failure notification on any error, so a scan is
 * never silently dropped, which is the spec's own stated minimum bar.
 */
class ViewAssetAuditSession extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AssetAuditSessionResource::class;

    protected string $view = 'filament.assets.pages.view-asset-audit-session';

    public string $scanInput = '';

    public string $tab = 'pending';

    public string $search = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn (): string => route('assets.export.audit-items', $this->record)),
        ];
    }

    public function getSession(): AssetAuditSession
    {
        /** @var AssetAuditSession $session */
        $session = $this->record;

        // ->load(), not ->loadMissing() — closeSession() sets closed_by
        // on this same in-memory instance after closedBy was already
        // cached as null (loaded on an earlier render, before the
        // asset was closed), and loadMissing() would never revisit an
        // already-loaded relation to pick up the new value.
        return $session->load(['startedBy', 'closedBy', 'scopeBuilding', 'scopeRoom']);
    }

    /**
     * @return Collection<int, AssetAuditItem>
     */
    public function items(): Collection
    {
        $query = AssetAuditItem::query()
            ->where('session_id', $this->getSession()->id)
            ->with(['asset', 'expectedRoom.building', 'foundRoom.building']);

        $query = match ($this->tab) {
            'verified' => $query->whereNotNull('verified_at'),
            'review' => $query->whereNotNull('outcome')->where('outcome', '!=', AssetAuditOutcome::Verified)->whereNull('review_action'),
            default => $query->whereNull('verified_at'),
        };

        if (filled($this->search)) {
            $query->whereHas('asset', fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('asset_tag', 'like', "%{$this->search}%"));
        }

        return $query->get();
    }

    /**
     * @return array{verified: int, total: int, review_remaining: int}
     */
    public function progress(): array
    {
        $session = $this->getSession();

        return [
            'verified' => AssetAuditItem::where('session_id', $session->id)->whereNotNull('verified_at')->count(),
            'total' => AssetAuditItem::where('session_id', $session->id)->count(),
            'review_remaining' => AssetAuditItem::where('session_id', $session->id)
                ->whereNotNull('outcome')->where('outcome', '!=', AssetAuditOutcome::Verified)->whereNull('review_action')
                ->count(),
        ];
    }

    /**
     * Called both by the manual "enter code" form and by the in-page
     * camera scanner (see the Blade view) — a QR code encodes the full
     * public URL (/a/{token}), so this accepts a bare token, a bare
     * asset tag, or that whole URL alike. Idempotent: re-submitting an
     * already-verified asset is a success message, not an error (spec
     * section 6.8, "Already verified").
     */
    public function verifyCode(?string $raw = null): void
    {
        $raw = trim($raw ?? $this->scanInput);
        $this->scanInput = '';

        if ($raw === '') {
            return;
        }

        $token = str($raw)->contains('/a/') ? (string) str($raw)->afterLast('/a/')->before('/') : $raw;

        $session = $this->getSession();

        if (! $session->isInProgress()) {
            Notification::make()->title('This audit session is closed.')->danger()->send();

            return;
        }

        if (! (AssetAuditSessionResource::userIsAssetAdminOrManager())) {
            Notification::make()->title('You do not have permission to verify assets.')->danger()->send();

            return;
        }

        $asset = Asset::query()->where('public_token', $token)->orWhere('asset_tag', $token)->first();

        if (! $asset) {
            Notification::make()->title("No asset matches \"{$raw}\".")->danger()->send();

            return;
        }

        $item = AssetAuditItem::query()->where('session_id', $session->id)->where('asset_id', $asset->id)->first();

        if (! $item) {
            Notification::make()->title("{$asset->name} is not part of this audit.")->warning()->send();

            return;
        }

        $this->markVerified($item, AssetAuditVerifyMethod::QrScan);
    }

    public function verifyManually(int $itemId): void
    {
        $session = $this->getSession();

        if (! $session->isInProgress() || ! AssetAuditSessionResource::userIsAssetAdminOrManager()) {
            return;
        }

        $item = AssetAuditItem::query()->where('session_id', $session->id)->findOrFail($itemId);

        $this->markVerified($item, AssetAuditVerifyMethod::ManualCheck);
    }

    private function markVerified(AssetAuditItem $item, AssetAuditVerifyMethod $method): void
    {
        if ($item->isVerified()) {
            Notification::make()->title("Already verified: {$item->asset->name}")->success()->send();

            return;
        }

        $session = $this->getSession();

        // The audit only has a "current room" signal when it's scoped
        // to one specific room — if the asset's expected room differs
        // from where the audit is physically happening, that's a
        // location mismatch, captured with no extra input needed. For
        // All/Building-scoped sessions there's no single "current
        // room," so no mismatch signal is captured at verify time.
        $foundRoomId = $session->scope_type === AssetAuditScopeType::Room
            ? $session->scope_room_id
            : $item->expected_room_id;

        try {
            $item->update([
                'verified_at' => now(),
                'verified_by' => auth()->id(),
                'verify_method' => $method,
                'found_room_id' => $foundRoomId,
            ]);
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Could not save this verification — please try again.')->danger()->send();

            return;
        }

        AssetHistory::record($item->asset_id, 'audit_verified', "Verified during audit: {$session->name}", 'audit_session', $session->id);

        Notification::make()->title("Verified: {$item->asset->name}")->success()->send();
    }

    public function closeSession(): void
    {
        $session = $this->getSession();

        if (! $session->isInProgress() || ! AssetAuditSessionResource::userIsAssetAdminOrManager()) {
            return;
        }

        AssetLock::once("asset-audit:{$session->id}", function () use ($session) {
            $session->refresh();

            if (! $session->isInProgress()) {
                return;
            }

            DB::transaction(function () use ($session) {
                $items = AssetAuditItem::where('session_id', $session->id)->get();

                foreach ($items as $item) {
                    $outcome = match (true) {
                        ! $item->isVerified() => AssetAuditOutcome::Missing,
                        $item->found_room_id !== null && $item->found_room_id !== $item->expected_room_id => AssetAuditOutcome::LocationMismatch,
                        default => AssetAuditOutcome::Verified,
                    };

                    $item->update(['outcome' => $outcome]);

                    if ($outcome === AssetAuditOutcome::Missing) {
                        AssetHistory::record($item->asset_id, 'audit_flagged_missing', "Not verified during audit: {$session->name}", 'audit_session', $session->id);
                    }
                }

                $session->update([
                    'status' => AssetAuditSessionStatus::Closed,
                    'closed_by' => auth()->id(),
                    'closed_at' => now(),
                ]);
            });
        });

        Notification::make()->title('Audit closed.')->success()->send();
    }

    public function reviewItem(int $itemId, string $action, ?string $note = null): void
    {
        if (! AssetAuditSessionResource::userIsAssetAdminOrManager()) {
            return;
        }

        $reviewAction = AssetAuditReviewAction::from($action);
        $session = $this->getSession();

        AssetLock::once("asset-audit-item:{$itemId}", function () use ($itemId, $session, $reviewAction, $note) {
            /** @var AssetAuditItem $item */
            $item = AssetAuditItem::query()->where('session_id', $session->id)->with('asset')->findOrFail($itemId);

            if (! $item->needsReview()) {
                return;
            }

            DB::transaction(function () use ($item, $reviewAction, $note, $session) {
                match ($reviewAction) {
                    AssetAuditReviewAction::MarkedLost => $this->applyMarkedLost($item),
                    AssetAuditReviewAction::LocationCorrected => $this->applyLocationCorrected($item),
                    AssetAuditReviewAction::KeptAsIs, AssetAuditReviewAction::Dismissed => null,
                };

                $item->update([
                    'review_action' => $reviewAction,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'review_note' => $note,
                ]);

                AssetHistory::record($item->asset_id, 'audit_reviewed', $note ?: $reviewAction->getLabel(), 'audit_session', $session->id);
            });
        });

        Notification::make()->title('Review recorded.')->success()->send();
    }

    private function applyMarkedLost(AssetAuditItem $item): void
    {
        $asset = $item->asset;
        $previousStatus = $asset->status;

        if ($previousStatus === AssetStatus::Lost) {
            return;
        }

        $asset->update(['status' => AssetStatus::Lost]);
        AssetHistory::recordFieldChange($asset->id, 'Status', $previousStatus->getLabel(), AssetStatus::Lost->getLabel(), 'status_changed');
    }

    private function applyLocationCorrected(AssetAuditItem $item): void
    {
        $asset = $item->asset;
        $newRoomId = $item->found_room_id ?? $item->expected_room_id;

        if ($newRoomId === $asset->room_id) {
            return;
        }

        $oldRoom = $asset->room;
        $asset->update(['room_id' => $newRoomId]);
        $asset->refresh();

        AssetHistory::recordFieldChange($asset->id, 'Location', $oldRoom?->path(), $asset->room?->path(), 'location_changed');
    }
}
