<?php

namespace Tests\Feature;

use App\Enums\AssetLifecycleStatus;
use App\Filament\Assets\Imports\AssetImporter;
use App\Models\Asset;
use App\Models\AssetBuilding;
use App\Models\AssetCategory;
use App\Models\AssetRoom;
use App\Models\AssetSetting;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_row_creates_a_draft_asset_with_a_generated_tag(): void
    {
        AssetSetting::set('council_code', '317');

        $top = AssetCategory::create(['name' => 'Furniture', 'gl_code' => '423001', 'is_active' => true]);
        AssetCategory::create(['name' => 'Office Furniture', 'parent_id' => $top->id, 'asset_class_code' => 'Z652', 'is_active' => true]);
        $building = AssetBuilding::create(['name' => 'Main Office', 'is_active' => true]);
        AssetRoom::create(['building_id' => $building->id, 'name' => 'Room 101', 'is_active' => true]);

        $importer = $this->makeImporter();

        $importer([
            'name' => 'Imported Chair',
            'category' => 'Z652',
            'building' => 'Main Office',
            'room' => 'Room 101',
            'purchase_date' => '2026-01-10',
        ]);

        $asset = Asset::where('name', 'Imported Chair')->firstOrFail();

        $this->assertSame(AssetLifecycleStatus::Draft, $asset->lifecycle_status);
        $this->assertStringStartsWith('317-26-Z652-', $asset->asset_tag);
        $this->assertSame('Room 101', $asset->room->name);
        $this->assertSame('purchased', $asset->asset_type->value);
    }

    public function test_a_row_with_an_unknown_room_fails_that_row_only(): void
    {
        $top = AssetCategory::create(['name' => 'Furniture', 'gl_code' => '423001', 'is_active' => true]);
        AssetCategory::create(['name' => 'Office Furniture', 'parent_id' => $top->id, 'asset_class_code' => 'Z652', 'is_active' => true]);
        AssetBuilding::create(['name' => 'Main Office', 'is_active' => true]);

        $importer = $this->makeImporter();

        $this->expectException(RowImportFailedException::class);

        $importer([
            'name' => 'Orphan Chair',
            'category' => 'Z652',
            'building' => 'Main Office',
            'room' => 'Nonexistent Room',
        ]);
    }

    private function makeImporter(): AssetImporter
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $import = Import::create([
            'file_name' => 'assets.csv',
            'file_path' => 'assets.csv',
            'importer' => AssetImporter::class,
            'total_rows' => 1,
            'user_id' => $user->id,
        ]);

        $columns = collect(AssetImporter::getColumns())
            ->mapWithKeys(fn ($column) => [$column->getName() => $column->getName()])
            ->all();

        return new AssetImporter($import, $columns, []);
    }
}
