<?php

namespace Tests\Feature;

use App\Enums\InventoryIssueRequestStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryRole;
use App\Enums\InventorySourceType;
use App\Filament\Inventory\Widgets\InventoryStatsOverview;
use App\Filament\Inventory\Widgets\MovementChartWidget;
use App\Filament\Inventory\Widgets\MyRecentRequestsWidget;
use App\Filament\Inventory\Widgets\PendingApprovalWidget;
use App\Filament\Inventory\Widgets\RecentMovementsWidget;
use App\Filament\Inventory\Widgets\ReorderAlertsWidget;
use App\Models\InventoryIssueRequest;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Models\InventoryRecipient;
use App\Models\InventoryUnitOfMeasure;
use App\Models\User;
use App\Services\Inventory\ReorderCalculationService;
use App\Services\Inventory\StockMovementService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryDashboardTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $location;

    private InventoryItemCategory $category;

    private InventoryUnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('inventory'));

        $this->location = InventoryLocation::create(['code' => 'MAIN', 'name' => 'Main Store', 'is_default' => true]);
        $this->category = InventoryItemCategory::create(['code' => 'STAT', 'name' => 'Stationery']);
        $this->uom = InventoryUnitOfMeasure::create(['code' => 'PC', 'name' => 'Piece', 'decimal_places' => 0]);
    }

    private function makeUser(InventoryRole $role): User
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => $role]);

        return $user;
    }

    private function makeItem(string $code, string $onHand, string $reorderLevel, array $overrides = []): InventoryItem
    {
        $item = InventoryItem::create([
            'code' => $code,
            'name' => "Item {$code}",
            'category_id' => $this->category->id,
            'uom_id' => $this->uom->id,
            'reorder_level' => $reorderLevel,
            ...$overrides,
        ]);
        $item->stock()->create(['location_id' => $this->location->id, 'on_hand' => 0, 'reserved' => 0]);

        if (bccomp($onHand, '0', 3) > 0) {
            app(StockMovementService::class)->record(
                item: $item,
                location: $this->location,
                type: InventoryMovementType::Opening,
                quantity: $onHand,
                performer: User::factory()->create(),
                sourceType: InventorySourceType::Opening,
            );
        }

        return $item;
    }

    public function test_candidate_items_includes_only_items_at_or_below_reorder_level(): void
    {
        $below = $this->makeItem('STAT-0001', '5', '10');
        $atLevel = $this->makeItem('STAT-0002', '10', '10');
        $above = $this->makeItem('STAT-0003', '20', '10');

        $candidates = app(ReorderCalculationService::class)->candidateItems();

        $this->assertTrue($candidates->contains('id', $below->id));
        $this->assertTrue($candidates->contains('id', $atLevel->id));
        $this->assertFalse($candidates->contains('id', $above->id));
    }

    public function test_candidate_items_excludes_inactive_untracked_and_zero_reorder_level_items(): void
    {
        $inactive = $this->makeItem('STAT-0001', '0', '10', ['is_active' => false]);
        $untracked = $this->makeItem('STAT-0002', '0', '10', ['is_stock_tracked' => false]);
        $noReorderLevel = $this->makeItem('STAT-0003', '0', '0');

        $candidates = app(ReorderCalculationService::class)->candidateItems();

        $this->assertFalse($candidates->contains('id', $inactive->id));
        $this->assertFalse($candidates->contains('id', $untracked->id));
        $this->assertFalse($candidates->contains('id', $noReorderLevel->id));
    }

    public function test_severity_boundaries_match_spec_thresholds(): void
    {
        $service = app(ReorderCalculationService::class);

        $this->assertSame('Out of stock', $service->severityForAvailable('0', '10')['label']);
        $this->assertSame('Critical', $service->severityForAvailable('5', '10')['label']);
        $this->assertSame('Low', $service->severityForAvailable('10', '10')['label']);
        $this->assertSame('OK', $service->severityForAvailable('11', '10')['label']);
        $this->assertSame('Not tracked', $service->severityForAvailable('5', '0')['label']);
    }

    public function test_suggested_qty_falls_back_through_the_three_tiers(): void
    {
        $service = app(ReorderCalculationService::class);

        $withReorderQty = $this->makeItem('STAT-0001', '2', '10', ['reorder_qty' => '25']);
        $this->assertSame('25', $service->suggestedQty($withReorderQty, '2'));

        $withMaxLevel = $this->makeItem('STAT-0002', '2', '10', ['max_level' => '30']);
        $this->assertSame('28', $service->suggestedQty($withMaxLevel, '2'));

        $withNeither = $this->makeItem('STAT-0003', '2', '10');
        // reorder_level * 2 - available = 20 - 2 = 18
        $this->assertSame('18', $service->suggestedQty($withNeither, '2'));
    }

    public function test_days_of_cover_is_null_without_usage_history(): void
    {
        $item = $this->makeItem('STAT-0001', '100', '10');

        $this->assertNull(app(ReorderCalculationService::class)->daysOfCover($item, '100'));
    }

    public function test_days_of_cover_reflects_recent_issue_activity(): void
    {
        $item = $this->makeItem('STAT-0001', '100', '10');
        $admin = User::factory()->create();

        // 30 issued over the 90-day window -> ~0.333/day -> ~300 days
        // of cover on the remaining 100.
        app(StockMovementService::class)->record(
            item: $item, location: $this->location, type: InventoryMovementType::Issue,
            quantity: '30', performer: $admin, sourceType: InventorySourceType::Adjustment,
        );

        $days = app(ReorderCalculationService::class)->daysOfCover($item, '70');

        $this->assertNotNull($days);
        $this->assertGreaterThan(0, (float) $days);
    }

    public function test_widget_visibility_is_gated_by_role(): void
    {
        $plainUser = $this->makeUser(InventoryRole::User);
        $approver = $this->makeUser(InventoryRole::Approver);
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);

        $this->actingAs($plainUser);
        $this->assertFalse(PendingApprovalWidget::canView());
        $this->assertFalse(ReorderAlertsWidget::canView());
        $this->assertFalse(MovementChartWidget::canView());
        $this->assertFalse(RecentMovementsWidget::canView());
        $this->assertTrue(InventoryStatsOverview::canView());
        $this->assertTrue(MyRecentRequestsWidget::canView());

        $this->actingAs($approver);
        $this->assertTrue(PendingApprovalWidget::canView());
        $this->assertFalse(ReorderAlertsWidget::canView());

        $this->actingAs($stockAdmin);
        $this->assertFalse(PendingApprovalWidget::canView());
        $this->assertTrue(ReorderAlertsWidget::canView());
        $this->assertTrue(MovementChartWidget::canView());
        $this->assertTrue(RecentMovementsWidget::canView());
    }

    public function test_pending_approval_widget_flags_a_line_that_exceeds_available_stock(): void
    {
        $approver = $this->makeUser(InventoryRole::Approver);
        $requester = $this->makeUser(InventoryRole::User);
        $item = $this->makeItem('STAT-0001', '5', '10');

        $request = InventoryIssueRequest::create([
            'request_no' => 'IR-2026-0001', 'request_date' => now(), 'requested_by' => $requester->id,
            'location_id' => $this->location->id, 'recipient_id' => InventoryRecipient::create(['name' => 'Jane Recipient'])->id,
            'purpose' => 'Test', 'status' => InventoryIssueRequestStatus::Submitted,
            'submitted_at' => now(),
        ]);
        $request->lines()->create(['line_no' => 1, 'item_id' => $item->id, 'requested_qty' => '20']);

        $this->actingAs($approver);

        Livewire::test(PendingApprovalWidget::class)
            ->assertSee('IR-2026-0001')
            ->assertSee('Needs decision');
    }

    public function test_pending_approval_widget_does_not_flag_a_fully_available_request(): void
    {
        $approver = $this->makeUser(InventoryRole::Approver);
        $requester = $this->makeUser(InventoryRole::User);
        $item = $this->makeItem('STAT-0001', '50', '10');

        $request = InventoryIssueRequest::create([
            'request_no' => 'IR-2026-0002', 'request_date' => now(), 'requested_by' => $requester->id,
            'location_id' => $this->location->id, 'recipient_id' => InventoryRecipient::create(['name' => 'Jane Recipient'])->id,
            'purpose' => 'Test', 'status' => InventoryIssueRequestStatus::Submitted,
            'submitted_at' => now(),
        ]);
        $request->lines()->create(['line_no' => 1, 'item_id' => $item->id, 'requested_qty' => '5']);

        $this->actingAs($approver);

        Livewire::test(PendingApprovalWidget::class)
            ->assertSee('IR-2026-0002')
            ->assertDontSee('Needs decision');
    }

    public function test_reorder_buying_list_route_streams_a_csv(): void
    {
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);
        $this->makeItem('STAT-0001', '2', '10');

        $response = $this->actingAs($stockAdmin)->get(route('inventory.reorder.buying-list'));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Supplier,Code,Name,Available', $content);
        $this->assertStringContainsString('Reorder Level', $content);
        $this->assertStringContainsString('Suggested Qty', $content);
        $this->assertStringContainsString('No supplier', $content);
        $this->assertStringContainsString('STAT-0001', $content);
    }

    public function test_reorder_buying_list_route_is_blocked_for_a_plain_user(): void
    {
        $user = $this->makeUser(InventoryRole::User);

        $this->actingAs($user)->get(route('inventory.reorder.buying-list'))->assertForbidden();
    }

    public function test_dashboard_route_renders_for_every_role(): void
    {
        foreach (InventoryRole::cases() as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)->get('/inventory')->assertOk();
        }
    }
}
