<?php

namespace App\Services\Inventory;

use App\Enums\InventoryRole;
use App\Filament\Inventory\Resources\Adjustments\AdjustmentResource;
use App\Filament\Inventory\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Filament\Inventory\Resources\Items\ItemResource;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryIssueRequest;
use App\Models\InventoryItem;
use App\Models\InventoryStockAdjustment;
use App\Models\User;
use App\Notifications\InventoryAlert;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Spec 13, trimmed to the events that fire from code this app already
 * has (see the Phase 8 plan's own "explicitly not built" list for what
 * this deliberately skips — the digest/reminder jobs need a scheduler
 * this app doesn't have). Every method enforces spec's "never notify
 * the person who triggered the action" rule before sending.
 */
class InventoryNotifier
{
    public function requestSubmitted(InventoryIssueRequest $request): void
    {
        $recipients = $this->usersWithRole(InventoryRole::Approver)->reject(fn (User $u): bool => $u->id === $request->requested_by);

        $this->alert(
            $recipients,
            "New request pending approval: {$request->request_no}",
            $request->purpose,
            IssueRequestResource::getUrl('view', ['record' => $request]),
        );
    }

    public function requestApproved(InventoryIssueRequest $request): void
    {
        $this->notifyRequester($request, "{$request->request_no} was approved.");
    }

    public function requestRejected(InventoryIssueRequest $request): void
    {
        $this->notifyRequester($request, "{$request->request_no} was rejected.", $request->rejection_reason);
    }

    public function requestReturnedForEdit(InventoryIssueRequest $request): void
    {
        $this->notifyRequester($request, "{$request->request_no} was returned to you for edits.");
    }

    public function itemsIssued(InventoryIssueRequest $request): void
    {
        $this->notifyRequester($request, "Items for {$request->request_no} have been issued.");
    }

    private function notifyRequester(InventoryIssueRequest $request, string $title, ?string $body = null): void
    {
        $requester = $request->requester ?? User::find($request->requested_by);

        if (! $requester || $requester->id === auth()->id()) {
            return;
        }

        $this->alert(collect([$requester]), $title, $body, IssueRequestResource::getUrl('view', ['record' => $request]));
    }

    public function adjustmentPendingApproval(InventoryStockAdjustment $adjustment): void
    {
        $recipients = $this->usersWithRole(InventoryRole::Admin)->reject(fn (User $u): bool => $u->id === auth()->id());

        $this->alert(
            $recipients,
            "Adjustment awaiting approval: {$adjustment->adjustment_no}",
            $adjustment->reason,
            AdjustmentResource::getUrl('view', ['record' => $adjustment]),
        );
    }

    public function grnPendingReversalApproval(InventoryGoodsReceipt $grn): void
    {
        $recipients = $this->usersWithRole(InventoryRole::Admin)->reject(fn (User $u): bool => $u->id === auth()->id());

        $this->alert(
            $recipients,
            "GRN reversal awaiting approval: {$grn->grn_no}",
            $grn->reversal_reason,
            GoodsReceiptResource::getUrl('view', ['record' => $grn]),
        );
    }

    /**
     * Called from StockMovementService::record() itself (the one place
     * every stock mutation already passes through) whenever an item's
     * reorder severity bucket gets worse — not on every movement, only
     * on an actual crossing, so a chronically low item doesn't spam.
     */
    public function reorderSeverityWorsened(InventoryItem $item, string $newSeverityLabel): void
    {
        $recipients = $this->usersWithRole(InventoryRole::StockAdmin)->reject(fn (User $u): bool => $u->id === auth()->id());

        $this->alert(
            $recipients,
            "{$item->code} is now {$newSeverityLabel}",
            $item->name,
            ItemResource::getUrl('view', ['record' => $item]),
        );
    }

    /**
     * Explicit InventoryUserRole holders plus the system-wide spatie
     * admin role — the same two groups every userIsXOrAbove() helper
     * in this module already treats as equivalent (hasInventoryRole()'s
     * own bypass).
     *
     * @return Collection<int, User>
     */
    private function usersWithRole(InventoryRole $role): Collection
    {
        $explicit = User::query()->whereHas('inventoryRoles', fn ($q) => $q->where('role', $role))->get();
        $systemAdmins = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();

        return $explicit->merge($systemAdmins)->unique('id')->values();
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function alert(Collection $recipients, string $title, ?string $body, ?string $url): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        // Not ->sendToDatabase() — Filament's own DatabaseNotification
        // class implements ShouldQueue, and this app has no queue
        // worker running (same "no scheduler/worker infra yet" gap
        // Phase 3/6's own on-demand-only commands already live with),
        // so a queued dispatch would just sit unprocessed in the jobs
        // table forever. sendNow() reuses Filament's own toDatabase()
        // formatting (the bell renders it identically) but delivers
        // synchronously, bypassing the queue entirely.
        $filamentNotification = Notification::make()
            ->title($title)
            ->body($body)
            ->when($url, fn (Notification $n) => $n->actions([
                Action::make('view')->label('View')->url($url),
            ]));

        NotificationFacade::sendNow($recipients, $filamentNotification->toDatabase());

        if (\App\Models\InventorySetting::getBool('email_notifications_enabled', true)) {
            foreach ($recipients as $recipient) {
                $recipient->notify(new InventoryAlert($title, $body, $url));
            }
        }
    }
}
