<?php

namespace Tests\Feature;

use App\Enums\AssetRole;
use App\Filament\Assets\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\AssetBuilding;
use App\Models\AssetCategory;
use App\Models\AssetRoom;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetQrCodeAndPublicPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('assets'));
    }

    private function makeAsset(array $overrides = []): Asset
    {
        $admin = User::factory()->create();
        $category = AssetCategory::firstOrCreate(['name' => 'Furniture'], ['is_active' => true]);
        $building = AssetBuilding::firstOrCreate(['name' => 'Main Office'], ['is_active' => true]);
        $room = AssetRoom::firstOrCreate(['building_id' => $building->id, 'name' => 'Room 101'], ['is_active' => true]);

        return Asset::create(array_merge([
            'asset_tag' => 'AST-000001',
            'public_token' => 'tok_'.str()->random(28),
            'name' => 'Office Chair',
            'category_id' => $category->id,
            'brand' => 'Herman Miller',
            'serial_number' => 'SN-SECRET-123',
            'purchase_price' => 999.99,
            'vendor' => 'Secret Vendor Co',
            'description' => 'Confidential internal note',
            'room_id' => $room->id,
            'status' => 'in_use',
            'created_by' => $admin->id,
        ], $overrides));
    }

    public function test_the_public_page_shows_only_the_five_permitted_fields(): void
    {
        $asset = $this->makeAsset();

        $response = $this->get(route('assets.public.show', $asset->public_token));

        $response->assertOk();
        $response->assertSee($asset->name);
        $response->assertSee($asset->asset_tag);
        $response->assertSee('In Use');
        $response->assertSee('Main Office');
        $response->assertSee('Room 101');

        // The five permitted fields only — never price, vendor, serial,
        // or notes (implementation plan section 3.4/8.25).
        $response->assertDontSee('999.99');
        $response->assertDontSee('Secret Vendor Co');
        $response->assertDontSee('SN-SECRET-123');
        $response->assertDontSee('Confidential internal note');
    }

    public function test_the_json_endpoint_returns_only_the_five_permitted_fields(): void
    {
        $asset = $this->makeAsset();

        $response = $this->get(route('assets.public.json', $asset->public_token));

        $response->assertOk();
        $response->assertJson([
            'name' => 'Office Chair',
            'asset_tag' => 'AST-000001',
            'status' => 'In Use',
            'building' => 'Main Office',
            'room' => 'Room 101',
        ]);

        $response->assertJsonMissingPath('purchase_price');
        $response->assertJsonMissingPath('vendor');
        $response->assertJsonMissingPath('serial_number');
        $response->assertJsonMissingPath('description');
    }

    public function test_an_unknown_token_returns_a_generic_404(): void
    {
        $this->get(route('assets.public.show', 'does-not-exist'))->assertNotFound();
        $this->get(route('assets.public.json', 'does-not-exist'))->assertNotFound();
        $this->get(route('assets.public.photo', 'does-not-exist'))->assertNotFound();
    }

    public function test_an_authenticated_asset_role_holder_scanning_the_public_url_is_redirected_to_the_full_page(): void
    {
        $asset = $this->makeAsset();
        $user = User::factory()->create();
        $user->assetRoles()->create(['role' => AssetRole::Viewer]);
        $this->actingAs($user);

        $response = $this->get(route('assets.public.show', $asset->public_token));

        $response->assertRedirect(route('filament.assets.resources.assets.view', ['record' => $asset]));
    }

    public function test_a_logged_out_visitor_can_see_the_public_page_without_authentication(): void
    {
        $asset = $this->makeAsset();

        $this->get(route('assets.public.show', $asset->public_token))->assertOk();
    }

    public function test_the_public_photo_endpoint_404s_when_the_asset_has_no_photo(): void
    {
        $asset = $this->makeAsset();

        $this->get(route('assets.public.photo', $asset->public_token))->assertNotFound();
    }

    public function test_the_qr_and_label_routes_require_authentication(): void
    {
        $asset = $this->makeAsset();

        $this->get(route('assets.qr', $asset))->assertRedirect(route('filament.admin.auth.login'));
        $this->get(route('assets.label', $asset))->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_a_user_with_an_asset_role_can_download_the_qr_code(): void
    {
        $asset = $this->makeAsset();
        $user = User::factory()->create();
        $user->assetRoles()->create(['role' => AssetRole::Viewer]);
        $this->actingAs($user);

        $response = $this->get(route('assets.qr', $asset));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
    }

    public function test_bulk_label_printing_requires_at_least_one_valid_id(): void
    {
        $user = User::factory()->create();
        $user->assetRoles()->create(['role' => AssetRole::Admin]);
        $this->actingAs($user);

        $this->get(route('assets.labels.bulk'))->assertNotFound();
        $this->get(route('assets.labels.bulk', ['ids' => '']))->assertNotFound();
    }

    public function test_bulk_label_printing_renders_a_label_per_selected_asset(): void
    {
        $assetA = $this->makeAsset(['asset_tag' => 'AST-000001', 'public_token' => 'tok_a_'.str()->random(20), 'name' => 'Chair A']);
        $assetB = $this->makeAsset(['asset_tag' => 'AST-000002', 'public_token' => 'tok_b_'.str()->random(20), 'name' => 'Chair B']);

        $user = User::factory()->create();
        $user->assetRoles()->create(['role' => AssetRole::Admin]);
        $this->actingAs($user);

        $response = $this->get(route('assets.labels.bulk', ['ids' => "{$assetA->id},{$assetB->id}"]));

        $response->assertOk();
        $response->assertSee('Chair A');
        $response->assertSee('Chair B');
    }

    /**
     * Regression test for a bug caught only via live browser
     * verification: the bulk action originally used ->url(), which
     * Filament evaluates once at table render time (before any row is
     * selected), so it always produced an empty id list. It must use
     * ->action() + redirect() instead, which runs at click time with
     * the real selection.
     */
    public function test_the_bulk_print_labels_action_redirects_with_the_selected_asset_ids(): void
    {
        $assetA = $this->makeAsset(['asset_tag' => 'AST-000001', 'public_token' => 'tok_a_'.str()->random(20), 'name' => 'Chair A']);
        $assetB = $this->makeAsset(['asset_tag' => 'AST-000002', 'public_token' => 'tok_b_'.str()->random(20), 'name' => 'Chair B']);

        $user = User::factory()->create();
        $user->assetRoles()->create(['role' => AssetRole::Admin]);
        $this->actingAs($user);

        Livewire::test(ListAssets::class)
            ->callTableBulkAction('printLabels', [$assetA->id, $assetB->id])
            ->assertRedirect(route('assets.labels.bulk', ['ids' => "{$assetA->id},{$assetB->id}"]));
    }
}
