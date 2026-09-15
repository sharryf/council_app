<?php

namespace Tests\Feature;

use App\Enums\AssetRole;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use App\Filament\Assets\Pages\Dashboard;
use App\Filament\Assets\Resources\Assets\Pages\ListAssets;
use App\Filament\Assets\Widgets\AssetStatsWidget;
use App\Filament\Assets\Widgets\OpenAuditWidget;
use App\Filament\Assets\Widgets\PendingTransfersWidget;
use App\Models\Asset;
use App\Models\AssetBuilding;
use App\Models\AssetCategory;
use App\Models\AssetRoom;
use App\Models\AssetTransferRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetDashboardAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('assets'));
    }

    private function makeUser(?AssetRole $role = null): User
    {
        $user = User::factory()->create();

        if ($role) {
            $user->assetRoles()->create(['role' => $role]);
        }

        return $user;
    }

    private function makeAsset(User $creator, AssetStatus $status, string $name = 'Office Chair'): Asset
    {
        $category = AssetCategory::firstOrCreate(['name' => 'Furniture'], ['is_active' => true]);
        $building = AssetBuilding::firstOrCreate(['name' => 'Main Office'], ['is_active' => true]);
        $room = AssetRoom::firstOrCreate(['building_id' => $building->id, 'name' => 'Room 101'], ['is_active' => true]);

        return Asset::create([
            'asset_tag' => 'AST-'.random_int(100000, 999999),
            'public_token' => str()->random(32),
            'name' => $name,
            'category_id' => $category->id,
            'room_id' => $room->id,
            'status' => $status,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * Regression test for a bug caught only via live browser
     * verification: OpenAuditWidget's Blade view rendered nothing at
     * all when there was no in-progress session, which Livewire
     * rejects (RootTagMissingFromViewException) — a component's view
     * must always render exactly one root element. Also covers the
     * whole Dashboard page, since that's what actually surfaced it.
     */
    public function test_the_dashboard_renders_without_error_when_no_audit_is_in_progress(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);

        Livewire::test(OpenAuditWidget::class)->assertOk();
        Livewire::test(Dashboard::class)->assertOk();
    }

    public function test_the_pending_transfers_widget_only_shows_pending_requests(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);

        $pendingAsset = $this->makeAsset($admin, AssetStatus::InUse, 'Pending Chair');
        $approvedAsset = $this->makeAsset($admin, AssetStatus::InUse, 'Approved Chair');
        $room = $pendingAsset->room;

        AssetTransferRequest::create([
            'asset_id' => $pendingAsset->id, 'from_room_id' => $room->id, 'to_room_id' => $room->id,
            'status' => AssetTransferStatus::Pending, 'requested_by' => $admin->id, 'requested_at' => now(),
        ]);
        AssetTransferRequest::create([
            'asset_id' => $approvedAsset->id, 'from_room_id' => $room->id, 'to_room_id' => $room->id,
            'status' => AssetTransferStatus::Approved, 'requested_by' => $admin->id, 'requested_at' => now(), 'decided_at' => now(), 'decided_by' => $admin->id,
        ]);

        Livewire::test(PendingTransfersWidget::class)
            ->assertSee('Pending Chair')
            ->assertDontSee('Approved Chair');
    }

    public function test_the_stats_widget_counts_assets_per_status(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);

        $this->makeAsset($admin, AssetStatus::InUse);
        $this->makeAsset($admin, AssetStatus::InUse);
        $this->makeAsset($admin, AssetStatus::Lost);

        Livewire::test(AssetStatsWidget::class)
            ->assertSee('3')
            ->assertSee('2')
            ->assertSee('1');
    }

    public function test_the_dashboard_status_deep_link_filters_the_asset_list(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);

        $lostAsset = $this->makeAsset($admin, AssetStatus::Lost, 'Lost Chair');
        $inUseAsset = $this->makeAsset($admin, AssetStatus::InUse, 'In Use Chair');

        Livewire::test(ListAssets::class, ['dashboardStatusFilter' => 'lost'])
            ->assertCanSeeTableRecords([$lostAsset])
            ->assertCanNotSeeTableRecords([$inUseAsset]);
    }

    public function test_the_assets_csv_export_requires_authentication(): void
    {
        $this->get(route('assets.export.assets'))->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_the_assets_csv_export_streams_a_csv_for_an_authorized_user(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $this->makeAsset($admin, AssetStatus::InUse, 'Exportable Chair');

        $response = $this->get(route('assets.export.assets'));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Exportable Chair', $content);
    }

    public function test_the_assets_csv_export_respects_the_status_filter(): void
    {
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);
        $this->makeAsset($admin, AssetStatus::InUse, 'In Use Chair');
        $this->makeAsset($admin, AssetStatus::Lost, 'Lost Chair');

        $response = $this->get(route('assets.export.assets', ['filters' => ['status' => ['values' => ['lost']]]]));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Lost Chair', $content);
        $this->assertStringNotContainsString('In Use Chair', $content);
    }
}
