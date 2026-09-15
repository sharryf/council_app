<?php

namespace App\Filament\Inventory\Pages;

use App\Enums\InventoryRole;
use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Filament\Inventory\Widgets\DashboardOverviewWidget;
use App\Filament\Inventory\Widgets\MyRecentRequestsWidget;
use App\Models\InventoryIssueRequest;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Schema;

/**
 * Inventory's own dashboard — role-aware layout per spec 10.8:
 * DashboardOverviewWidget decides internally which cards a given
 * viewer sees (and whether the Low Stock sidebar renders at all),
 * while MyRecentRequestsWidget's own canView() gates it to any role
 * holder.
 *
 * Extends Filament's own Dashboard (not a plain Page), same as
 * App\Filament\Bureau\Pages\Dashboard, so it becomes the panel's index
 * route (/inventory). $isDiscovered is false for the same reason as
 * that one: this class also lives under the discovered Pages
 * directory, and letting both discovery and the explicit
 * InventoryPanelProvider::pages() registration claim the same route
 * would collide.
 */
class Dashboard extends BaseDashboard
{
    use HasInventoryRoleAccess;

    protected static bool $isDiscovered = false;

    protected static ?int $navigationSort = 1;

    public function getTitle(): string
    {
        return 'Inventory';
    }

    public function getHeading(): string
    {
        return 'Inventory';
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    /**
     * Any of the four inventory roles (or the system-wide admin, via
     * hasInventoryRole()'s own bypass) may reach the panel; a user with
     * none of them is denied. This is the check Phase 1's access test
     * exercises.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && collect(InventoryRole::cases())->contains(fn (InventoryRole $role): bool => $user->hasInventoryRole($role));
    }

    /**
     * A "New Issue Request" quick action for whoever actually raises
     * requests — Stock Admins (who also fulfil them) and plain Users.
     * Deliberately left off for an Approver-only viewer, matching this
     * module's existing separation-of-duties stance elsewhere (e.g.
     * AdjustmentResource's creator-≠-approver rule): the person
     * approving a request shouldn't also be the one raising it.
     *
     * IssueRequestResource::createAction() normally infers its model
     * and form from the owning ListRecords page's own resource context
     * — a plain Dashboard page has none, so both are set explicitly
     * here (without this, the button opened a blank modal and errored
     * on submit).
     */
    protected function getHeaderActions(): array
    {
        return [
            IssueRequestResource::createAction()
                ->label('New Issue Request')
                ->model(InventoryIssueRequest::class)
                ->schema(fn (Schema $schema): Schema => IssueRequestResource::form($schema))
                ->visible(fn (): bool => self::userIsStockAdminOrAbove() || (auth()->user()?->hasInventoryRole(InventoryRole::User) ?? false)),
        ];
    }

    /**
     * Fixed order (not panel-level auto-discovery — see Bureau's own
     * Dashboard::getWidgets() for why that leaks unrelated widgets onto
     * every page): the role-aware overview cards + Low Stock sidebar
     * first, then every role's own recent requests last. The older
     * per-role widgets this replaced (PendingApprovalWidget,
     * ReorderAlertsWidget, InventoryStatsOverview, MovementChartWidget,
     * RecentMovementsWidget) still exist and are still tested — they're
     * just no longer registered here.
     *
     * @return array<class-string<\Filament\Widgets\Widget>>
     */
    public function getWidgets(): array
    {
        return [
            DashboardOverviewWidget::class,
            MyRecentRequestsWidget::class,
        ];
    }
}
