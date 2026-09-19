<?php

namespace App\Filament\Assets\Resources\Audits\Pages;

use App\Enums\AssetAuditOutcome;
use App\Enums\AssetAuditScopeType;
use App\Enums\AssetAuditSessionStatus;
use App\Enums\AssetAuditVerifyMethod;
use App\Enums\AssetTransferStatus;
use App\Filament\Assets\Resources\Audits\AssetAuditSessionResource;
use App\Models\Asset;
use App\Models\AssetAuditItem;
use App\Models\AssetAuditSession;
use App\Models\AssetBuilding;
use App\Models\AssetHistory;
use App\Models\AssetRoom;
use App\Models\AssetTransferRequest;
use App\Services\Assets\AssetLock;
use App\Services\Assets\AssetNotifier;
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
 * outcome).
 *
 * An audit never edits an asset's condition or room directly. When an
 * item turns up somewhere unexpected, the checklist offers two ways to
 * record that: foundInAnotherRoom() ("move it here" — raises an
 * ordinary AssetTransferRequest, reason "Found in audit", for a
 * Manager to decide, same as every other room change in this module)
 * or foundMisplaced() ("it's misplaced, return it" — just a note, no
 * request, since the room of record isn't changing). A plain
 * verification can be walked back with undoVerification(); either of
 * the above cannot, once made. There's no post-close review queue: an
 * item left unverified when the session closes is simply tagged
 * AssetAuditOutcome::Missing ("Not Found in this Audit") and that's the
 * end of it.
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

    // A Room-scoped session is already narrowed to one room, so neither
    // filter applies there — only All/Building scope spans more than
    // one location worth narrowing further on the checklist.
    public ?int $filterBuildingId = null;

    public ?int $filterRoomId = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    /**
     * Filament's default "View {model label}" heading reads oddly here
     * ("View Asset Audit Session") — the session's own name is already
     * shown prominently in the page body, so this just drops "View".
     */
    public function getTitle(): string
    {
        return 'Asset Audit Session';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn (): string => route('assets.export.audit-items', $this->record)),
            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->url(fn (): string => route('assets.export.audit-pdf', $this->record))
                ->openUrlInNewTab(),
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
        $session = $this->getSession();

        $query = AssetAuditItem::query()
            ->where('session_id', $session->id)
            ->with(['asset', 'expectedRoom.building', 'foundRoom.building']);

        $query = match ($this->tab) {
            'verified' => $query->whereNotNull('verified_at'),
            default => $query->whereNull('verified_at'),
        };

        if (filled($this->search)) {
            $query->whereHas('asset', fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('asset_tag', 'like', "%{$this->search}%"));
        }

        if ($session->scope_type === AssetAuditScopeType::All && $this->filterBuildingId) {
            $query->whereHas('expectedRoom', fn ($q) => $q->where('building_id', $this->filterBuildingId));
        }

        // Available (and applied) on both All and Building scope — a
        // Room-scoped session is the only one already narrow enough not
        // to need it.
        if ($this->filterRoomId) {
            $query->where('expected_room_id', $this->filterRoomId);
        }

        return $query->get();
    }

    /**
     * A previously-picked room may not belong to the newly-picked
     * building, so it's cleared alongside — wire:click can only call
     * one method, hence this wrapping both $set()s the Blade view would
     * otherwise need to chain.
     */
    public function selectFilterBuilding(?int $buildingId): void
    {
        $this->filterBuildingId = $buildingId;
        $this->filterRoomId = null;
    }

    /**
     * @return array<int, string>
     */
    public function filterBuildingOptions(): array
    {
        return AssetBuilding::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Building scope: every room in that one building (name only — the
     * building is already implied). All scope: every room, further
     * narrowed to the selected building filter when one is picked, and
     * labelled with its building path since it isn't implied here.
     *
     * @return array<int, string>
     */
    public function filterRoomOptions(): array
    {
        $session = $this->getSession();

        if ($session->scope_type === AssetAuditScopeType::Building) {
            return AssetRoom::query()
                ->where('building_id', $session->scope_building_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        return AssetRoom::query()
            ->where('is_active', true)
            ->when($this->filterBuildingId, fn ($q) => $q->where('building_id', $this->filterBuildingId))
            ->with('building')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (AssetRoom $room): array => [$room->id => $room->path()])
            ->all();
    }

    /**
     * Every active room the "Found in Another Room" button can offer
     * for one item — the asset's own current room is excluded, same as
     * AssetResource::requestTransferAction()'s own room picker (no
     * point "moving" it to where it already is).
     *
     * @return array<int, string>
     */
    public function foundInRoomOptions(AssetAuditItem $item): array
    {
        if (! $item->asset) {
            return [];
        }

        return AssetRoom::query()
            ->where('is_active', true)
            ->whereKeyNot($item->asset->room_id)
            ->with('building')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (AssetRoom $room): array => [$room->id => $room->path()])
            ->all();
    }

    /**
     * @return array{verified: int, total: int}
     */
    public function progress(): array
    {
        $session = $this->getSession();

        return [
            'verified' => AssetAuditItem::where('session_id', $session->id)->whereNotNull('verified_at')->count(),
            'total' => AssetAuditItem::where('session_id', $session->id)->count(),
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
        if (! $item->asset) {
            Notification::make()->title('This asset no longer exists and cannot be verified.')->danger()->send();

            return;
        }

        if ($item->isVerified()) {
            Notification::make()->title("Already verified: {$item->asset->name}")->success()->send();

            return;
        }

        $session = $this->getSession();

        // The audit only has a "current room" signal when it's scoped
        // to one specific room — if the asset's expected room differs
        // from where the audit is physically happening, that's a
        // location mismatch, captured with no extra input needed (and
        // purely informational — see foundInAnotherRoom() for the
        // action a mismatch actually needs). For All/Building-scoped
        // sessions there's no single "current room," so no mismatch
        // signal is captured at plain-verify time.
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

    /**
     * The "it's here, just not where expected" counterpart to
     * verifyManually() — the asset is confirmed present (so the item is
     * verified, same as a plain check), but rather than silently
     * recording a mismatch for someone to sort out later, this raises
     * an ordinary AssetTransferRequest on the spot (reason "Found in
     * audit"), so the room correction goes through the exact same
     * Manager-approval path as any other move. Never touches
     * $asset->room_id itself — that only ever happens once that
     * request is approved (AssetTransferRequestResource::approveAction()).
     */
    public function foundInAnotherRoom(int $itemId, int $foundRoomId): void
    {
        $session = $this->getSession();

        if (! $session->isInProgress() || ! AssetAuditSessionResource::userIsAssetAdminOrManager()) {
            return;
        }

        AssetLock::once("asset-audit-item:{$itemId}", function () use ($itemId, $session, $foundRoomId) {
            /** @var AssetAuditItem $item */
            $item = AssetAuditItem::query()->where('session_id', $session->id)->with('asset')->findOrFail($itemId);

            if ($item->isVerified()) {
                Notification::make()->title('Already verified: '.($item->asset->name ?? 'this asset'))->success()->send();

                return;
            }

            $asset = $item->asset;

            if (! $asset) {
                Notification::make()->title('This asset no longer exists and cannot be verified.')->danger()->send();

                return;
            }

            if ($asset->hasPendingTransfer()) {
                Notification::make()->title('This asset already has a pending transfer request — refresh and check its current state.')->danger()->send();

                return;
            }

            $request = DB::transaction(function () use ($item, $asset, $foundRoomId, $session): AssetTransferRequest {
                $item->update([
                    'verified_at' => now(),
                    'verified_by' => auth()->id(),
                    'verify_method' => AssetAuditVerifyMethod::FoundElsewhere,
                    'found_room_id' => $foundRoomId,
                ]);

                $request = AssetTransferRequest::create([
                    'asset_id' => $asset->id,
                    'from_room_id' => $asset->room_id,
                    'to_room_id' => $foundRoomId,
                    'reason' => 'Found in audit',
                    'status' => AssetTransferStatus::Pending,
                    'requested_by' => auth()->id(),
                    'requested_at' => now(),
                ]);

                AssetHistory::record($asset->id, 'audit_verified', "Found in a different room during audit: {$session->name}.", 'audit_session', $session->id);
                AssetHistory::record($asset->id, 'transfer_requested', "Requested move to {$request->toRoom->path()} (found in audit).", 'transfer_request', $request->id);

                return $request;
            });

            app(AssetNotifier::class)->transferRequested($request->fresh(['asset', 'requestedBy', 'toRoom']));

            Notification::make()->title('Verified — a change-location request was submitted for Manager approval.')->success()->send();
        });
    }

    /**
     * The "it was found elsewhere, but it belongs back where it was
     * expected" counterpart to foundInAnotherRoom() — same verified-
     * with-a-note shape, but deliberately raises no transfer request:
     * the asset's room of record isn't changing, someone's just
     * physically returning it, so there's nothing for a Manager to
     * approve.
     */
    public function foundMisplaced(int $itemId, int $foundRoomId): void
    {
        $session = $this->getSession();

        if (! $session->isInProgress() || ! AssetAuditSessionResource::userIsAssetAdminOrManager()) {
            return;
        }

        AssetLock::once("asset-audit-item:{$itemId}", function () use ($itemId, $session, $foundRoomId) {
            /** @var AssetAuditItem $item */
            $item = AssetAuditItem::query()->where('session_id', $session->id)->with(['asset', 'expectedRoom'])->findOrFail($itemId);

            if ($item->isVerified()) {
                Notification::make()->title('Already verified: '.($item->asset->name ?? 'this asset'))->success()->send();

                return;
            }

            if (! $item->asset) {
                Notification::make()->title('This asset no longer exists and cannot be verified.')->danger()->send();

                return;
            }

            $foundRoom = AssetRoom::find($foundRoomId);

            $item->update([
                'verified_at' => now(),
                'verified_by' => auth()->id(),
                'verify_method' => AssetAuditVerifyMethod::Misplaced,
                'found_room_id' => $foundRoomId,
            ]);

            AssetHistory::record(
                $item->asset_id,
                'audit_verified',
                "Found misplaced in {$foundRoom?->path()} during audit: {$session->name} — to be returned to {$item->expectedRoom->path()}.",
                'audit_session',
                $session->id,
            );

            Notification::make()->title('Verified — marked misplaced, to be returned to '.$item->expectedRoom->path().'.')->success()->send();
        });
    }

    /**
     * Undoes a plain Verify/scan only — a misclick is easy to make when
     * working through a long checklist. A verification that came with a
     * room-change decision (foundInAnotherRoom()/foundMisplaced()) isn't
     * offered this button at all (see the Blade view) and is rejected
     * here too, since undoing it cleanly would also mean unwinding a
     * transfer request that may already be decided.
     */
    public function undoVerification(int $itemId): void
    {
        $session = $this->getSession();

        if (! $session->isInProgress() || ! AssetAuditSessionResource::userIsAssetAdminOrManager()) {
            return;
        }

        AssetLock::once("asset-audit-item:{$itemId}", function () use ($itemId, $session) {
            /** @var AssetAuditItem $item */
            $item = AssetAuditItem::query()->where('session_id', $session->id)->with('asset')->findOrFail($itemId);

            if (! $item->isVerified()) {
                return;
            }

            if (! $item->verify_method?->isUndoable()) {
                Notification::make()->title('This verification came with a location change and can\'t be undone here.')->danger()->send();

                return;
            }

            $item->update([
                'verified_at' => null,
                'verified_by' => null,
                'verify_method' => null,
                'found_room_id' => null,
            ]);

            AssetHistory::record($item->asset_id, 'audit_verification_undone', "Verification undone during audit: {$session->name}", 'audit_session', $session->id);

            Notification::make()->title('Verification undone: '.($item->asset->name ?? 'this asset'))->success()->send();
        });
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
                        AssetHistory::record($item->asset_id, 'audit_flagged_missing', "Not found during audit: {$session->name}", 'audit_session', $session->id);
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
}
