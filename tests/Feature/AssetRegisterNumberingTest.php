<?php

namespace Tests\Feature;

use App\Enums\AssetRole;
use App\Filament\Assets\Resources\Assets\Pages\CreateAsset;
use App\Models\Asset;
use App\Models\AssetBuilding;
use App\Models\AssetCategory;
use App\Models\AssetRoom;
use App\Models\AssetSetting;
use App\Models\User;
use App\Services\Assets\AssetTagGenerator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AssetRegisterNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('assets'));
    }

    public function test_it_formats_the_main_and_item_inventory_numbers_from_the_council_code_and_year(): void
    {
        AssetSetting::set('council_code', '317');

        $numbers = (new AssetTagGenerator)->generate(Carbon::parse('2023-06-15'), 'Z550');

        $this->assertSame('317-23-Z550-1', $numbers['main_inventory_no']);
        $this->assertSame('317-23-Z550-1-1', $numbers['asset_tag']);
        $this->assertSame(1, $numbers['main_sequence']);
    }

    public function test_the_main_sequence_counts_per_year_regardless_of_class(): void
    {
        AssetSetting::set('council_code', '317');
        $generator = new AssetTagGenerator;

        $first = $generator->generate(Carbon::parse('2023-01-01'), 'Z550');
        $second = $generator->generate(Carbon::parse('2023-06-01'), 'Z599');
        $thirdYear = $generator->generate(Carbon::parse('2024-01-01'), 'Z550');

        $this->assertSame('317-23-Z550-1', $first['main_inventory_no']);
        $this->assertSame('317-23-Z599-2', $second['main_inventory_no']);
        $this->assertSame('317-24-Z550-1', $thirdYear['main_inventory_no']);
    }

    public function test_it_falls_back_to_the_current_year_when_no_purchase_date_is_given(): void
    {
        $numbers = (new AssetTagGenerator)->generate(null, 'Z550');

        $this->assertStringContainsString('-'.now()->format('y').'-Z550-', $numbers['main_inventory_no']);
    }

    public function test_it_extracts_the_fund_code_from_the_po_number(): void
    {
        $generator = new AssetTagGenerator;

        $this->assertSame('J-GOM', $generator->fundCodeFromPoNumber('PO-1359/J-GOM/2023/0012'));
        $this->assertNull($generator->fundCodeFromPoNumber(null));
        $this->assertNull($generator->fundCodeFromPoNumber(''));
        $this->assertNull($generator->fundCodeFromPoNumber('no-slashes-here'));
    }

    public function test_creating_an_asset_through_the_form_derives_the_fund_code_from_the_po_number(): void
    {
        Storage::fake('local');
        AssetSetting::set('council_code', '317');

        $top = AssetCategory::create(['name' => 'Furniture', 'gl_code' => '423001', 'is_active' => true]);
        $category = AssetCategory::create(['name' => 'Office Furniture', 'parent_id' => $top->id, 'asset_class_code' => 'Z652', 'is_active' => true]);
        $building = AssetBuilding::create(['name' => 'Main Office', 'is_active' => true]);
        $room = AssetRoom::create(['building_id' => $building->id, 'name' => 'Room 101', 'is_active' => true]);

        $user = User::factory()->create();
        $user->assetRoles()->create(['role' => AssetRole::Admin]);
        $this->actingAs($user);

        Livewire::test(CreateAsset::class)
            ->fillForm([
                'photo' => UploadedFile::fake()->image('chair.jpg'),
                'name' => 'Office Chair',
                'category_id' => $category->id,
                'room_id' => $room->id,
                'status' => 'in_use',
                'purchase_date' => '2023-06-15',
                'po_number' => 'PO-1359/J-GOM/2023/0012',
                'voucher_number' => 'PV-1359/J-GOM/2023/0028',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var Asset $asset */
        $asset = Asset::where('name', 'Office Chair')->firstOrFail();

        $this->assertSame('317-23-Z652-1', $asset->main_inventory_no);
        $this->assertSame('317-23-Z652-1-1', $asset->asset_tag);
        $this->assertSame('J-GOM', $asset->fund_code);
    }

    public function test_po_number_and_voucher_number_are_required_when_purchased(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeFurnitureCategoryAndRoom();
        $this->actingAs($this->makeAdmin());

        Livewire::test(CreateAsset::class)
            ->fillForm([
                'photo' => UploadedFile::fake()->image('chair.jpg'),
                'name' => 'Office Chair',
                'category_id' => $category->id,
                'room_id' => $room->id,
                'status' => 'in_use',
                'asset_type' => 'purchased',
            ])
            ->call('create')
            ->assertHasFormErrors(['po_number', 'voucher_number']);
    }

    public function test_donation_reference_no_is_required_when_donated_and_po_fields_are_not(): void
    {
        Storage::fake('local');

        [$category, $room] = $this->makeFurnitureCategoryAndRoom();
        $this->actingAs($this->makeAdmin());

        Livewire::test(CreateAsset::class)
            ->fillForm([
                'photo' => UploadedFile::fake()->image('chair.jpg'),
                'name' => 'Donated Chair',
                'category_id' => $category->id,
                'room_id' => $room->id,
                'status' => 'in_use',
                'asset_type' => 'donated',
            ])
            ->call('create')
            ->assertHasFormErrors(['donation_reference_no']);

        Livewire::test(CreateAsset::class)
            ->fillForm([
                'photo' => UploadedFile::fake()->image('chair2.jpg'),
                'name' => 'Donated Chair 2',
                'category_id' => $category->id,
                'room_id' => $room->id,
                'status' => 'in_use',
                'asset_type' => 'donated',
                'donation_reference_no' => 'DON-2026-4',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('DON-2026-4', Asset::where('name', 'Donated Chair 2')->firstOrFail()->donation_reference_no);
    }

    public function test_category_is_optional_and_falls_back_to_a_generic_class_code(): void
    {
        Storage::fake('local');

        [, $room] = $this->makeFurnitureCategoryAndRoom();
        $this->actingAs($this->makeAdmin());

        Livewire::test(CreateAsset::class)
            ->fillForm([
                'photo' => UploadedFile::fake()->image('mystery.jpg'),
                'name' => 'Uncategorized Item',
                'room_id' => $room->id,
                'status' => 'in_use',
                'asset_type' => 'donated',
                'donation_reference_no' => 'DON-2026-9',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $asset = Asset::where('name', 'Uncategorized Item')->firstOrFail();

        $this->assertNull($asset->category_id);
        $this->assertStringContainsString('-GEN-', $asset->asset_tag);
    }

    public function test_the_create_another_button_is_not_offered(): void
    {
        $this->actingAs($this->makeAdmin());

        Livewire::test(CreateAsset::class)->assertDontSee('Create & create another');
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->assetRoles()->create(['role' => AssetRole::Admin]);

        return $user;
    }

    /**
     * @return array{0: AssetCategory, 1: AssetRoom}
     */
    private function makeFurnitureCategoryAndRoom(): array
    {
        $top = AssetCategory::create(['name' => 'Furniture', 'gl_code' => '423001', 'is_active' => true]);
        $category = AssetCategory::create(['name' => 'Office Furniture', 'parent_id' => $top->id, 'asset_class_code' => 'Z652', 'is_active' => true]);
        $building = AssetBuilding::create(['name' => 'Main Office', 'is_active' => true]);
        $room = AssetRoom::create(['building_id' => $building->id, 'name' => 'Room 101', 'is_active' => true]);

        return [$category, $room];
    }
}
