<?php

namespace Database\Seeders;

use App\Models\AssetSetting;
use Illuminate\Database\Seeder;

class AssetSettingSeeder extends Seeder
{
    public function run(): void
    {
        AssetSetting::query()->updateOrCreate(['key' => 'council_code'], ['value' => '317']);
    }
}
