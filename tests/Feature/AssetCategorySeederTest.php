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

        $this->assertSame(21, AssetCategory::whereNull('parent_id')->count());
        $this->assertSame(110, AssetCategory::whereNotNull('parent_id')->count());

        $land = AssetCategory::where('name', 'Land')->whereNull('parent_id')->first();
        $this->assertSame('421001', $land->gl_code);

        $agricultural = AssetCategory::where('name', 'Land - Agricultural')->first();
        $this->assertSame('Z001', $agricultural->asset_class_code);
        $this->assertSame($land->id, $agricultural->parent_id);
    }

    public function test_running_it_twice_does_not_duplicate_rows(): void
    {
        (new AssetCategorySeeder)->run();
        (new AssetCategorySeeder)->run();

        $this->assertSame(131, AssetCategory::count());
    }
}
