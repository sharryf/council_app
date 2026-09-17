<?php

namespace Tests\Feature;

use App\Enums\AssetRole;
use App\Filament\Assets\Resources\Assets\AssetResource;
use App\Filament\Assets\Resources\Assets\Pages\CreateAsset;
use App\Filament\Assets\Resources\Categories\AssetCategoryResource;
use App\Filament\Assets\Resources\Categories\Pages\CreateAssetCategory;
use App\Filament\Assets\Resources\Categories\Pages\EditAssetCategory;
use App\Models\Asset;
use App\Models\AssetBuilding;
use App\Models\AssetCategory;
use App\Models\AssetHistory;
use App\Models\AssetRoom;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AssetModuleTest extends TestCase
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

    private function makeCategoryAndRoom(): array
    {
        $top = AssetCategory::create(['name' => 'Furniture', 'gl_code' => '423001', 'is_active' => true]);
        $category = AssetCategory::create(['name' => 'Office Furniture', 'parent_id' => $top->id, 'asset_class_code' => 'Z652', 'is_active' => true]);
        $building = AssetBuilding::create(['name' => 'Main Office', 'is_active' => true]);
        $room = AssetRoom::create(['building_id' => $building->id, 'name' => 'Room 101', 'is_active' => true]);

        return [$category, $room];
    }

    public function test_a_user_with_no_asset_role_cannot_access_the_module(): void
    {
        $this->actingAs($this->makeUser());

        $this->assertFalse(AssetResource::canAccess());
        $this->assertFalse(AssetCategoryResource::canAccess());
    }

    public function test_only_admin_can_create_assets_manager_and_viewer_cannot(): void
    {
        $this->actingAs($this->makeUser(AssetRole::Admin));
        $this->assertTrue(AssetResource::canCreate());

        $this->actingAs($this->makeUser(AssetRole::Manager));
        $this->assertTrue(AssetResource::canAccess());
        $this->assertFalse(AssetResource::canCreate());

        $this->actingAs($this->makeUser(AssetRole::Viewer));
        $this->assertTrue(AssetResource::canAccess());
        $this->assertFalse(AssetResource::canCreate());
    }

    public function test_creating_an_asset_with_a_photo_generates_a_tag_and_logs_history(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $this->actingAs($this->makeUser(AssetRole::Admin));

        Livewire::test(CreateAsset::class)
            ->fillForm([
                'photo' => UploadedFile::fake()->image('laptop.jpg'),
                'name' => 'Dell Laptop',
                'category_top_id' => $category->parent_id,
                'category_id' => $category->id,
                'building_top_id' => $room->building_id,
                'room_id' => $room->id,
                'status' => 'in_use',
                'po_number' => 'PO-1000/J-GOM/2026/0001',
                'voucher_number' => 'PV-1000/J-GOM/2026/0001',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var Asset $asset */
        $asset = Asset::where('name', 'Dell Laptop')->firstOrFail();

        $this->assertMatchesRegularExpression('/^000-\d{2}-Z652-\d+-1$/', $asset->asset_tag);
        $this->assertSame($asset->main_inventory_no.'-1', $asset->asset_tag);
        $this->assertNotEmpty($asset->public_token);
        $this->assertNotNull($asset->photo_attachment_id);
        $this->assertNotNull($asset->photoAttachment);
        Storage::disk('local')->assertExists($asset->photoAttachment->file_path);

        $this->assertTrue(
            AssetHistory::where('asset_id', $asset->id)->where('event_type', 'created')->exists(),
        );
    }

    public function test_creating_an_asset_without_a_photo_fails(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $this->actingAs($this->makeUser(AssetRole::Admin));

        Livewire::test(CreateAsset::class)
            ->fillForm([
                'name' => 'No Photo Asset',
                'category_id' => $category->id,
                'room_id' => $room->id,
                'status' => 'in_use',
            ])
            ->call('create')
            ->assertHasFormErrors(['photo']);

        $this->assertFalse(Asset::where('name', 'No Photo Asset')->exists());
    }

    public function test_a_category_cannot_be_nested_more_than_two_levels_deep(): void
    {
        $this->actingAs($this->makeUser(AssetRole::Admin));

        $top = AssetCategory::create(['name' => 'Furniture', 'is_active' => true]);
        $sub = AssetCategory::create(['name' => 'Chairs', 'parent_id' => $top->id, 'is_active' => true]);

        Livewire::test(CreateAssetCategory::class)
            ->fillForm([
                'parent_id' => $sub->id,
                'name' => 'Office Chairs',
            ])
            ->call('create')
            ->assertHasFormErrors(['parent_id']);
    }

    public function test_a_category_referenced_by_an_asset_cannot_be_deactivated(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeCategoryAndRoom();
        $admin = $this->makeUser(AssetRole::Admin);
        $this->actingAs($admin);

        Asset::create([
            'asset_tag' => 'AST-000001',
            'public_token' => 'tok_'.str()->random(20),
            'name' => 'Existing Chair',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'status' => 'in_use',
            'created_by' => $admin->id,
        ]);

        Livewire::test(EditAssetCategory::class, ['record' => $category->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save');

        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_a_sub_categorys_path_includes_its_asset_class_code(): void
    {
        $top = AssetCategory::create(['name' => 'Land', 'gl_code' => '421001', 'is_active' => true]);
        $sub = AssetCategory::create([
            'name' => 'Land - Agricultural',
            'parent_id' => $top->id,
            'asset_class_code' => 'Z001',
            'is_active' => true,
        ]);

        $this->assertSame('Land > Land - Agricultural (Z001)', $sub->path());
        $this->assertSame('Land', $top->path());
    }

    public function test_two_sub_categories_cannot_share_an_asset_class_code(): void
    {
        $this->actingAs($this->makeUser(AssetRole::Admin));

        $top = AssetCategory::create(['name' => 'Land', 'is_active' => true]);
        AssetCategory::create(['name' => 'Land - Agricultural', 'parent_id' => $top->id, 'asset_class_code' => 'Z001', 'is_active' => true]);

        Livewire::test(CreateAssetCategory::class)
            ->fillForm([
                'parent_id' => $top->id,
                'name' => 'Land - Duplicate',
                'asset_class_code' => 'Z001',
            ])
            ->call('create')
            ->assertHasFormErrors(['asset_class_code']);
    }
}
