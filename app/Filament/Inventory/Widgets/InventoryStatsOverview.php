<?php

namespace App\Filament\Inventory\Widgets;

use App\Enums\InventoryGoodsReceiptStatus;
use App\Enums\InventoryIssueRequestStatus;
use App\Enums\InventoryRole;
use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryIssueRequest;
use App\Models\InventoryItem;
use App\Models\InventoryUserRole;
use App\Models\User;
use App\Services\Inventory\ReorderCalculationService;
use App\Services\Inventory\StockMovementService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Role-conditional KPI tiles (spec 10.8) — one widget rather than one
 * per role, since Filament's StatsOverviewWidget already supports a
 * dynamic tile set per getStats() call; every tile that drills through
 * links straight to the matching pre-filtered resource list.
 *
 * Only the raw computed values are cached (spec's 60s dashboard-cache
 * rule) — Stat objects themselves carry internal Closures/bound state
 * that aren't safe to pass through Cache::remember(), so getStats()
 * always builds fresh Stat::make() calls from cached plain data.
 */
class InventoryStatsOverview extends StatsOverviewWidget
{
    use HasInventoryRoleAccess;

    // Widgets are lazy-loaded by default (an x-intersect follow-up
    // request after the page's own load) — same fix Phase 4's
    // MovementsRelationManager needed, for the same reason: that
    // follow-up request doesn't reliably fire in this environment,
    // leaving the widget stuck on "Loading...".
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return self::userHasAnyInventoryRole();
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        /** @var User $user */

        $isApprover = self::userIsApproverOrAbove();
        $isStockAdmin = self::userIsStockAdminOrAbove();
        $isAdmin = $user->hasInventoryRole(InventoryRole::Admin);

        $data = Cache::remember(
            "inventory.dashboard.stats.{$user->id}",
            60,
            fn (): array => [
                'my' => $this->computeMyRequestStats($user),
                'approver' => $isApprover ? $this->computeApproverStats($user) : null,
                'stockAdmin' => $isStockAdmin ? $this->computeStockAdminStats() : null,
                'admin' => $isAdmin ? $this->computeAdminStats() : null,
            ],
        );

        return [
            ...$this->myRequestStatTiles($data['my']),
            ...($data['approver'] ? $this->approverStatTiles($data['approver']) : []),
            ...($data['stockAdmin'] ? $this->stockAdminStatTiles($data['stockAdmin']) : []),
            ...($data['admin'] ? $this->adminStatTiles($data['admin']) : []),
        ];
    }

    private function computeMyRequestStats(User $user): array
    {
        $counts = InventoryIssueRequest::query()
            ->where('requested_by', $user->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'draft' => (int) ($counts[InventoryIssueRequestStatus::Draft->value] ?? 0),
            'submitted' => (int) ($counts[InventoryIssueRequestStatus::Submitted->value] ?? 0),
            'approved' => (int) ($counts[InventoryIssueRequestStatus::Approved->value] ?? 0)
                + (int) ($counts[InventoryIssueRequestStatus::PartiallyIssued->value] ?? 0),
            'issued' => (int) ($counts[InventoryIssueRequestStatus::Issued->value] ?? 0),
        ];
    }

    private function myRequestStatTiles(array $d): array
    {
        return [
            Stat::make('My Draft Requests', $d['draft'])->url(IssueRequestResource::getUrl('index')),
            Stat::make('My Pending Requests', $d['submitted'])->url(IssueRequestResource::getUrl('index')),
            Stat::make('My Approved Requests', $d['approved'])->url(IssueRequestResource::getUrl('index')),
            Stat::make('My Issued Requests', $d['issued'])->url(IssueRequestResource::getUrl('index')),
        ];
    }

    private function computeApproverStats(User $user): array
    {
        $approvedThisMonth = InventoryIssueRequest::query()
            ->where('approver_id', $user->id)
            ->where('approved_at', '>=', now()->startOfMonth())
            ->count();

        $rejectedRecently = InventoryIssueRequest::query()
            ->where('approver_id', $user->id)
            ->where('status', InventoryIssueRequestStatus::Rejected)
            ->where('rejected_at', '>=', now()->subDays(30))
            ->count();

        $avgTurnaroundHours = InventoryIssueRequest::query()
            ->where('approver_id', $user->id)
            ->whereNotNull('approved_at')
            ->whereNotNull('submitted_at')
            ->where('approved_at', '>=', now()->subDays(90))
            ->get(['submitted_at', 'approved_at'])
            ->avg(fn (InventoryIssueRequest $r): float => $r->submitted_at->diffInHours($r->approved_at));

        return [
            'approvedThisMonth' => $approvedThisMonth,
            'rejectedRecently' => $rejectedRecently,
            'avgTurnaroundHours' => $avgTurnaroundHours,
        ];
    }

    private function approverStatTiles(array $d): array
    {
        return [
            Stat::make('Approved This Month', $d['approvedThisMonth']),
            Stat::make('Avg Approval Turnaround', $d['avgTurnaroundHours'] ? round($d['avgTurnaroundHours'], 1).'h' : '—'),
            Stat::make('Rejected Recently', $d['rejectedRecently']),
        ];
    }

    private function computeStockAdminStats(): array
    {
        $service = app(ReorderCalculationService::class);
        $candidates = $service->candidateItems();

        $outOfStock = $candidates->filter(fn (InventoryItem $i): bool => bccomp($service->availableFor($i), '0', 3) <= 0)->count();

        return [
            'outOfStock' => $outOfStock,
            'lowStock' => $candidates->count() - $outOfStock,
            'totalActive' => InventoryItem::query()->where('is_active', true)->count(),
            'readyToIssue' => InventoryIssueRequest::query()
                ->whereIn('status', [InventoryIssueRequestStatus::Approved, InventoryIssueRequestStatus::PartiallyIssued])
                ->count(),
            'pendingDrafts' => InventoryGoodsReceipt::query()->where('status', InventoryGoodsReceiptStatus::Draft)->count(),
        ];
    }

    private function stockAdminStatTiles(array $d): array
    {
        return [
            Stat::make('Out of Stock', $d['outOfStock'])->color($d['outOfStock'] > 0 ? 'danger' : 'success'),
            Stat::make('Low Stock', $d['lowStock'])->color($d['lowStock'] > 0 ? 'warning' : 'success'),
            Stat::make('Total Active Items', $d['totalActive']),
            Stat::make('Ready to Issue', $d['readyToIssue'])->url(IssueRequestResource::getUrl('index')),
            Stat::make('Pending Draft Receipts', $d['pendingDrafts'])->url(GoodsReceiptResource::getUrl('index')),
        ];
    }

    private function computeAdminStats(): array
    {
        $byRole = InventoryUserRole::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return [
            'approvers' => (int) ($byRole[InventoryRole::Approver->value] ?? 0),
            'stockAdmins' => (int) ($byRole[InventoryRole::StockAdmin->value] ?? 0),
            'unassigned' => User::query()->whereDoesntHave('inventoryRoles')->count(),
            'drift' => app(StockMovementService::class)->reconcileDrift()->count(),
        ];
    }

    private function adminStatTiles(array $d): array
    {
        return [
            Stat::make('Approvers', $d['approvers']),
            Stat::make('Stock Admins', $d['stockAdmins']),
            Stat::make('Users With No Inventory Role', $d['unassigned']),
            Stat::make('Balance Reconciliation', $d['drift'] === 0 ? 'Clean' : "{$d['drift']} item(s) drifting")
                ->color($d['drift'] === 0 ? 'success' : 'danger'),
        ];
    }
}
