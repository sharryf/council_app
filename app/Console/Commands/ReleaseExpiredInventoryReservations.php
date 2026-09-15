<?php

namespace App\Console\Commands;

use App\Enums\InventoryIssueRequestStatus;
use App\Models\InventoryIssueRequest;
use App\Models\InventorySetting;
use App\Services\Inventory\StockMovementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Spec's reservation_expiry_days setting wants a nightly sweep of stale
 * Approved/PartiallyIssued requests — same call
 * ReconcileInventoryBalances made for the ledger: no scheduled-job
 * infrastructure exists in this app yet, so this is on-demand only, not
 * wired to real cron.
 */
class ReleaseExpiredInventoryReservations extends Command
{
    protected $signature = 'inventory:release-expired-reservations';

    protected $description = 'Expire stale approved issue requests and release their outstanding reservation.';

    public function handle(StockMovementService $movements): int
    {
        $days = (int) (InventorySetting::get('reservation_expiry_days') ?: 7);
        $cutoff = now()->subDays($days);

        $requests = InventoryIssueRequest::query()
            ->whereIn('status', [InventoryIssueRequestStatus::Approved, InventoryIssueRequestStatus::PartiallyIssued])
            ->where('approved_at', '<=', $cutoff)
            ->with(['lines.item', 'location'])
            ->get();

        if ($requests->isEmpty()) {
            $this->info('No expired reservations.');

            return self::SUCCESS;
        }

        foreach ($requests as $request) {
            DB::transaction(function () use ($request, $movements, $days) {
                foreach ($request->lines as $line) {
                    $outstanding = bcsub((string) ($line->approved_qty ?? '0'), (string) $line->issued_qty, 3);

                    if (bccomp($outstanding, '0', 3) > 0) {
                        $movements->release($line->item, $request->location, $outstanding);
                    }
                }

                $request->update(['status' => InventoryIssueRequestStatus::Expired]);

                $request->approvalActions()->create([
                    'action' => 'EXPIRED',
                    'action_by' => $request->approver_id ?? $request->requested_by,
                    'action_at' => now(),
                    'remarks' => "Auto-expired after {$days} days without collection.",
                ]);
            });

            $this->line("Expired {$request->request_no}");
        }

        $this->info("{$requests->count()} request(s) expired.");

        return self::SUCCESS;
    }
}
