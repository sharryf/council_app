<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryRole;
use App\Enums\InventorySourceType;
use App\Filament\Inventory\Pages\InventoryReports;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Models\InventoryUnitOfMeasure;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryReportsTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('inventory'));

        $this->location = InventoryLocation::create(['code' => 'MAIN', 'name' => 'Main Store', 'is_default' => true]);
    }

    private function makeUser(InventoryRole $role): User
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => $role]);

        return $user;
    }

    private function makeItem(string $code, string $onHand = '0', string $reorderLevel = '0'): InventoryItem
    {
        $category = InventoryItemCategory::query()->firstOrCreate(['code' => 'STAT'], ['name' => 'Stationery']);
        $uom = InventoryUnitOfMeasure::query()->firstOrCreate(['code' => 'PC'], ['name' => 'Piece', 'decimal_places' => 0]);

        $item = InventoryItem::create([
            'code' => $code, 'name' => "Item {$code}", 'category_id' => $category->id, 'uom_id' => $uom->id,
            'reorder_level' => $reorderLevel,
        ]);
        $item->stock()->create(['location_id' => $this->location->id, 'on_hand' => 0, 'reserved' => 0]);

        if (bccomp($onHand, '0', 3) > 0) {
            app(StockMovementService::class)->record(
                item: $item, location: $this->location, type: InventoryMovementType::Opening,
                quantity: $onHand, performer: User::factory()->create(), sourceType: InventorySourceType::Opening,
            );
        }

        return $item;
    }

    public function test_a_plain_user_cannot_reach_the_reports_page(): void
    {
        $user = $this->makeUser(InventoryRole::User);

        $this->actingAs($user)->get(InventoryReports::getUrl())->assertForbidden();
    }

    public function test_a_stock_admin_can_reach_the_reports_page(): void
    {
        $this->actingAs($this->makeUser(InventoryRole::StockAdmin))
            ->get(InventoryReports::getUrl())
            ->assertSuccessful();
    }

    /**
     * Regression test: table() builds a fresh Table on every call, but
     * InteractsWithTable caches the rendered result per component
     * lifetime — switching $activeReport alone left the rendered
     * table showing whichever report loaded first, only caught by
     * asserting on actual rendered *content* here, not just the
     * property value (see setActiveReport()'s resetTable() call).
     */
    public function test_switching_reports_changes_the_rendered_table(): void
    {
        $this->makeItem('STAT-0001', '10');
        $this->actingAs($this->makeUser(InventoryRole::StockAdmin));

        Livewire::test(InventoryReports::class)
            ->assertSet('activeReport', 'stock-balance')
            ->assertSeeText('Reorder Level')
            ->call('setActiveReport', 'stock-movement')
            ->assertSet('activeReport', 'stock-movement')
            ->assertSeeText('Balance')
            ->assertDontSeeText('Reorder Level');
    }

    public function test_stock_balance_export_contains_the_expected_row(): void
    {
        $this->makeItem('STAT-0001', '25');
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);

        $response = $this->actingAs($stockAdmin)->get(route('inventory.reports.export', ['report' => 'stock-balance']));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Report: Stock Balance', $content);
        $this->assertStringContainsString('Generated:', $content);
        $this->assertStringContainsString('STAT-0001', $content);
        $this->assertStringContainsString(',25,', $content);
        $this->assertStringNotContainsString('25.000', $content);
    }

    public function test_reorder_export_contains_only_items_below_reorder_level(): void
    {
        $this->makeItem('STAT-0001', '2', '10'); // below
        $this->makeItem('STAT-0002', '50', '10'); // above
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);

        $response = $this->actingAs($stockAdmin)->get(route('inventory.reports.export', ['report' => 'reorder']));
        $content = $response->streamedContent();

        $this->assertStringContainsString('STAT-0001', $content);
        $this->assertStringNotContainsString('STAT-0002', $content);
    }

    public function test_stock_balance_export_respects_the_category_filter(): void
    {
        $stationery = InventoryItemCategory::query()->firstOrCreate(['code' => 'STAT'], ['name' => 'Stationery']);
        $electrical = InventoryItemCategory::query()->firstOrCreate(['code' => 'ELEC'], ['name' => 'Electrical']);
        $uom = InventoryUnitOfMeasure::query()->firstOrCreate(['code' => 'PC'], ['name' => 'Piece', 'decimal_places' => 0]);

        $stationeryItem = InventoryItem::create(['code' => 'STAT-0001', 'name' => 'Stationery Item', 'category_id' => $stationery->id, 'uom_id' => $uom->id]);
        $stationeryItem->stock()->create(['location_id' => $this->location->id, 'on_hand' => 5, 'reserved' => 0]);

        $electricalItem = InventoryItem::create(['code' => 'ELEC-0001', 'name' => 'Electrical Item', 'category_id' => $electrical->id, 'uom_id' => $uom->id]);
        $electricalItem->stock()->create(['location_id' => $this->location->id, 'on_hand' => 5, 'reserved' => 0]);

        $response = $this->actingAs($this->makeUser(InventoryRole::StockAdmin))->get(route('inventory.reports.export', [
            'report' => 'stock-balance',
            'filters' => ['category_id' => ['value' => $stationery->id]],
        ]));
        $content = $response->streamedContent();

        $this->assertStringContainsString('STAT-0001', $content);
        $this->assertStringNotContainsString('ELEC-0001', $content);
    }

    public function test_stock_balance_export_respects_the_hide_zero_stock_toggle_being_turned_off(): void
    {
        $item = $this->makeItem('STAT-0001', '0');

        $withoutZeroStock = $this->actingAs($this->makeUser(InventoryRole::StockAdmin))
            ->get(route('inventory.reports.export', ['report' => 'stock-balance']))
            ->streamedContent();
        $this->assertStringNotContainsString('STAT-0001', $withoutZeroStock);

        $withZeroStock = $this->actingAs($this->makeUser(InventoryRole::StockAdmin))
            ->get(route('inventory.reports.export', [
                'report' => 'stock-balance',
                'filters' => ['hide_zero_stock' => ['isActive' => false]],
            ]))
            ->streamedContent();
        $this->assertStringContainsString('STAT-0001', $withZeroStock);
    }

    public function test_stock_movement_export_respects_the_date_range_filter(): void
    {
        $item = $this->makeItem('STAT-0001', '10');

        app(StockMovementService::class)->record(
            item: $item, location: $this->location, type: \App\Enums\InventoryMovementType::AdjustIn,
            quantity: '5', performer: User::factory()->create(), sourceType: InventorySourceType::Adjustment,
            sourceNo: 'ADJ-OLD',
        );

        $response = $this->actingAs($this->makeUser(InventoryRole::StockAdmin))->get(route('inventory.reports.export', [
            'report' => 'stock-movement',
            'filters' => ['movement_date' => ['from' => now()->addDay()->toDateString()]],
        ]));
        $content = $response->streamedContent();

        $this->assertStringNotContainsString('ADJ-OLD', $content);
    }

    public function test_issue_summary_export_respects_the_status_filter(): void
    {
        $requester = $this->makeUser(InventoryRole::StockAdmin);

        \App\Models\InventoryIssueRequest::create([
            'request_no' => 'IR-DRAFT-0001', 'request_date' => now(), 'requested_by' => $requester->id,
            'location_id' => $this->location->id, 'purpose' => 'Draft one', 'status' => \App\Enums\InventoryIssueRequestStatus::Draft,
        ]);
        \App\Models\InventoryIssueRequest::create([
            'request_no' => 'IR-SUBMITTED-0001', 'request_date' => now(), 'requested_by' => $requester->id,
            'location_id' => $this->location->id, 'purpose' => 'Submitted one', 'status' => \App\Enums\InventoryIssueRequestStatus::Submitted,
        ]);

        $response = $this->actingAs($this->makeUser(InventoryRole::StockAdmin))->get(route('inventory.reports.export', [
            'report' => 'issue-summary',
            'filters' => ['status' => ['value' => \App\Enums\InventoryIssueRequestStatus::Submitted->value]],
        ]));
        $content = $response->streamedContent();

        $this->assertStringContainsString('IR-SUBMITTED-0001', $content);
        $this->assertStringNotContainsString('IR-DRAFT-0001', $content);
    }

    public function test_export_is_blocked_for_a_plain_user(): void
    {
        $user = $this->makeUser(InventoryRole::User);

        $this->actingAs($user)
            ->get(route('inventory.reports.export', ['report' => 'stock-balance']))
            ->assertForbidden();
    }

    public function test_pdf_export_is_blocked_for_a_plain_user(): void
    {
        $user = $this->makeUser(InventoryRole::User);

        $this->actingAs($user)
            ->get(route('inventory.reports.export-pdf', ['report' => 'stock-balance']))
            ->assertForbidden();
    }
}
