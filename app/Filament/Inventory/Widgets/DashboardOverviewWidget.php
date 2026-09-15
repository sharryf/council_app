<?php

namespace App\Filament\Inventory\Widgets;

use App\Enums\InventoryAdjustmentStatus;
use App\Enums\InventoryIssueRequestStatus;
use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\Adjustments\AdjustmentResource;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Filament\Inventory\Pages\InventoryReports;
use App\Models\InventoryIssueRequest;
use App\Models\InventoryStockAdjustment;
use App\Models\User;
use App\Services\Inventory\ReorderCalculationService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;

/**
 * The redesigned dashboard's main section — a compact, role-aware
 * layout: a stack of number cards on the left (colored when there's
 * something pending) and a tall Low Stock list on the right, replacing
 * the separate PendingApprovalWidget/ReorderAlertsWidget/
 * InventoryStatsOverview tiles that used to live on this page. Those
 * widgets are untouched and still fully tested — they're just no
 * longer registered in Dashboard::getWidgets(), so their queries stay
 * reusable (see pendingIssueCount()/pendingApprovalsCount() below,
 * which mirror InventoryStatsOverview's own definitions) without
 * duplicating business logic.
 */
class DashboardOverviewWidget extends Widget
{
    use HasInventoryRoleAccess;

    protected string $view = 'filament.inventory.widgets.dashboard-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return self::userHasAnyInventoryRole();
    }

    /**
     * @return array<int, array{label: string, count: int, icon: string, url: ?string, links: ?array<int, array{label: string, count: int, url: string}>}>
     */
    public function getCards(): array
    {
        $user = auth()->user();
        /** @var User $user */

        $isApprover = self::userIsApproverOrAbove();
        $isStockAdmin = self::userIsStockAdminOrAbove();

        $data = Cache::remember(
            "inventory.dashboard.overview.{$user->id}",
            60,
            fn (): array => [
                'my' => $this->myCounts($user),
                'pendingIssue' => ($isApprover || $isStockAdmin) ? $this->pendingIssueCount() : null,
                'pendingApprovals' => ($isApprover || $isStockAdmin) ? $this->pendingApprovalsBreakdown() : null,
            ],
        );

        $cards = [];

        if ($data['pendingIssue'] !== null) {
            $cards[] = [
                'label' => 'Pending Issue',
                'count' => $data['pendingIssue'],
                'icon' => 'heroicon-o-archive-box-arrow-down',
                'url' => IssueRequestResource::getUrl('index', ['tab' => 'ready_to_issue']),
                'links' => null,
            ];
        }

        if ($data['pendingApprovals'] !== null) {
            $breakdown = $data['pendingApprovals'];

            $cards[] = [
                'label' => $isApprover ? 'My Pending Approvals' : 'Pending Approvals',
                'count' => $breakdown['issueRequests'] + $breakdown['adjustments'],
                'icon' => 'heroicon-o-clock',
                // Spans two resources — the combined number itself isn't
                // a link, but each half below is its own.
                'url' => null,
                'links' => [
                    ['label' => 'Issue Requests', 'count' => $breakdown['issueRequests'], 'url' => IssueRequestResource::getUrl('index', ['tab' => 'pending_approval'])],
                    ['label' => 'Adjustments', 'count' => $breakdown['adjustments'], 'url' => AdjustmentResource::getUrl('index', ['tab' => 'pending_approval'])],
                ],
            ];
        }

        $cards[] = [
            'label' => 'My Drafts',
            'count' => $data['my']['draft'],
            'icon' => 'heroicon-o-document-text',
            'url' => IssueRequestResource::getUrl('index', ['tab' => 'mine', 'status' => InventoryIssueRequestStatus::Draft->value]),
            'links' => null,
        ];

        $cards[] = [
            'label' => 'My Pending Requests',
            'count' => $data['my']['submitted'],
            'icon' => 'heroicon-o-paper-airplane',
            'url' => IssueRequestResource::getUrl('index', ['tab' => 'mine', 'status' => InventoryIssueRequestStatus::Submitted->value]),
            'links' => null,
        ];

        return $cards;
    }

    public function showLowStock(): bool
    {
        return self::userIsStockAdminOrAbove() || self::userIsApproverOrAbove();
    }

    /**
     * The Reorder Report page is Stock-Admin-only (InventoryReports::
     * canAccess()) — narrower than showLowStock(), which also lets
     * Approvers see this card — so the heading only links through for
     * viewers who can actually open it.
     */
    public function getLowStockReportUrl(): ?string
    {
        return self::userIsStockAdminOrAbove() ? InventoryReports::getUrl(['activeReport' => 'reorder']) : null;
    }

    /**
     * @return array<int, array{name: string, qty: string}>
     */
    public function getLowStockItems(): array
    {
        if (! $this->showLowStock()) {
            return [];
        }

        $service = app(ReorderCalculationService::class);

        return Cache::remember(
            'inventory.dashboard.overview.low-stock',
            60,
            fn (): array => $service->candidateItems()
                ->map(fn ($item): array => [
                    'name' => $item->name,
                    'qty' => Number::format((float) $service->availableFor($item)),
                ])
                ->all(),
        );
    }

    /**
     * @return array{draft: int, submitted: int}
     */
    private function myCounts(User $user): array
    {
        $counts = InventoryIssueRequest::query()
            ->where('requested_by', $user->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'draft' => (int) ($counts[InventoryIssueRequestStatus::Draft->value] ?? 0),
            'submitted' => (int) ($counts[InventoryIssueRequestStatus::Submitted->value] ?? 0),
        ];
    }

    /**
     * Same definition as InventoryStatsOverview's 'readyToIssue' —
     * approved (or partially issued) requests waiting for a Stock
     * Admin to actually hand out the goods.
     */
    private function pendingIssueCount(): int
    {
        return InventoryIssueRequest::query()
            ->whereIn('status', [InventoryIssueRequestStatus::Approved, InventoryIssueRequestStatus::PartiallyIssued])
            ->count();
    }

    /**
     * Issue requests awaiting approval (same definition
     * PendingApprovalWidget's own table uses) plus adjustments awaiting
     * approval — both are an open pool (no per-approver assignment), so
     * this count is identical for every approver. Kept as a breakdown
     * (not just a sum) so the card can still link each half to its own
     * filtered list.
     *
     * @return array{issueRequests: int, adjustments: int}
     */
    private function pendingApprovalsBreakdown(): array
    {
        return [
            'issueRequests' => InventoryIssueRequest::query()
                ->where('status', InventoryIssueRequestStatus::Submitted)
                ->count(),
            'adjustments' => InventoryStockAdjustment::query()
                ->where('status', InventoryAdjustmentStatus::PendingApproval)
                ->count(),
        ];
    }
}
