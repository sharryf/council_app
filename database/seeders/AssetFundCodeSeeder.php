<?php

namespace Database\Seeders;

use App\Models\AssetFundCode;
use Illuminate\Database\Seeder;

class AssetFundCodeSeeder extends Seeder
{
    public function run(): void
    {
        AssetFundCode::query()->updateOrCreate(
            ['code' => 'J-GOM'],
            ['name' => 'Government of Maldives', 'is_active' => true],
        );
    }
}
