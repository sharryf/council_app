<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use Database\Seeders\AssetCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_official_asset_classification(): void
    {
        (new AssetCategorySeeder)->run();

        $this->assertSame(13, AssetCategory::whereNull('parent_id')->count());
        $this->assertSame(66, AssetCategory::whereNotNull('parent_id')->count());

        $furniture = AssetCategory::where('name', 'Furniture & Fittings')->whereNull('parent_id')->first();
        $this->assertSame('423001', $furniture->gl_code);

        $officeFurniture = AssetCategory::where('asset_class_code', 'Z652')->first();
        $this->assertSame('Office Furniture', $officeFurniture->name);
        $this->assertSame($furniture->id, $officeFurniture->parent_id);
        $this->assertSame('Furniture & Fittings > Office Furniture (Z652)', $officeFurniture->path());
    }

    /**
     * Land, Roads and Bridges, Airports, Wharves/Ports/Harbours, Water
     * & Sanitation Systems, Electricity Systems, Communication
     * Infrastructure, and Aerospace equipment were deliberately dropped
     * from the source PDF's full classification (not every category is
     * relevant to this council) — regression-tested so a re-add of the
     * PDF's tree data doesn't silently reintroduce them.
     */
    public function test_it_does_not_seed_the_categories_removed_on_request(): void
    {
        (new AssetCategorySeeder)->run();

        $removed = [
            'Land', 'Roads and Bridges', 'Airports', 'Wharves, Ports and Harbours',
            'Water & Sanitation Systems', 'Electricity Systems',
            'Communication Infrastructure', 'Aerospace equipment',
        ];

        $this->assertSame(0, AssetCategory::whereIn('name', $removed)->whereNull('parent_id')->count());
    }

    public function test_running_it_twice_does_not_duplicate_rows(): void
    {
        (new AssetCategorySeeder)->run();
        (new AssetCategorySeeder)->run();

        $this->assertSame(79, AssetCategory::count());
    }

    /**
     * Regression test: children were originally keyed by name, so a
     * name correction on re-seed (e.g. this seeder's own "Land -
     * Agricultural" → "Agricultural" fix) would have created a second,
     * orphaned row instead of renaming the existing one. Keying by
     * asset_class_code instead means a rename always updates in place.
     */
    public function test_renaming_a_child_in_the_seeder_updates_the_existing_row_on_reseed(): void
    {
        (new AssetCategorySeeder)->run();

        $officeFurniture = AssetCategory::where('asset_class_code', 'Z652')->firstOrFail();
        $officeFurniture->update(['name' => 'Furniture & Fittings - Office Furniture']);

        (new AssetCategorySeeder)->run();

        $this->assertSame(79, AssetCategory::count());
        $this->assertSame('Office Furniture', $officeFurniture->fresh()->name);
    }
}
