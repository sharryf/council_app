<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetBuilding;
use App\Models\AssetCategory;
use App\Models\AssetRoom;
use App\Models\User;
use App\Services\Assets\AssetTagGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample/demo data for exercising the Assets module — NOT part of
 * DatabaseSeeder's default run (this is fake data, not a fresh-install
 * default), so it's only added when explicitly requested:
 * `php artisan db:seed --class=AssetSampleDataSeeder`.
 *
 * Every asset goes through the real AssetTagGenerator (same code path
 * CreateAsset uses), so MainInventoryNo/ItemInventoryNo/FundCode come
 * out exactly as they would from the UI — this doubles as a sanity
 * check of that numbering logic across a spread of years/classes.
 * Re-running this adds another 50 assets rather than upserting; it's a
 * one-shot demo seed, not idempotent reference data like
 * AssetCategorySeeder.
 */
class AssetSampleDataSeeder extends Seeder
{
    private const ASSET_COUNT = 50;

    /**
     * name, category (must match a seeded AssetCategory sub-category
     * name), rough price range in MVR.
     */
    private const ITEM_POOL = [
        ['name' => 'Dell Optiplex Desktop PC', 'category' => 'Computers', 'price' => [8000, 15000]],
        ['name' => 'HP EliteDesk Desktop PC', 'category' => 'Computers', 'price' => [7500, 14000]],
        ['name' => 'Acer 22" Monitor', 'category' => 'Computers', 'price' => [1800, 3200]],
        ['name' => 'HP LaserJet Pro Printer', 'category' => 'Printers', 'price' => [3500, 9000]],
        ['name' => 'Canon Document Scanner', 'category' => 'Scanners', 'price' => [2500, 6000]],
        ['name' => 'Epson Projector', 'category' => 'Projectors', 'price' => [9000, 18000]],
        ['name' => 'TP-Link Network Switch (24 Port)', 'category' => 'Switches', 'price' => [2000, 5500]],
        ['name' => 'Netgear Wireless Router', 'category' => 'Routers', 'price' => [1200, 3000]],
        ['name' => 'Dell PowerEdge Server', 'category' => 'Servers', 'price' => [25000, 60000]],
        ['name' => 'Synology NAS DiskStation', 'category' => 'Other IT Equipment', 'price' => [8000, 16000]],
        ['name' => 'Fingerprint Attendance Machine', 'category' => 'Other IT Equipment', 'price' => [2500, 5000]],
        ['name' => 'UPS Backup Unit', 'category' => 'Other IT Equipment', 'price' => [3000, 7000]],
        ['name' => 'Office Desk (Wooden)', 'category' => 'Office Furniture', 'price' => [1500, 4000]],
        ['name' => 'Executive Chair (Black)', 'category' => 'Office Furniture', 'price' => [1200, 3500]],
        ['name' => 'Filing Cabinet (4 Drawer)', 'category' => 'Office Furniture', 'price' => [2000, 4500]],
        ['name' => 'Conference Table', 'category' => 'Office Furniture', 'price' => [5000, 12000]],
        ['name' => 'Visitor Sofa Set', 'category' => 'Office Furniture', 'price' => [6000, 14000]],
        ['name' => 'Wooden Cabinet (3 Drawer)', 'category' => 'Office Furniture', 'price' => [1800, 3800]],
        ['name' => 'Plastic Chair (White)', 'category' => 'Office Furniture', 'price' => [300, 800]],
        ['name' => 'Standing Fan', 'category' => 'Other Fittings', 'price' => [800, 2000]],
        ['name' => 'Ceiling Fan', 'category' => 'Other Fittings', 'price' => [900, 2200]],
        ['name' => 'Whiteboard (Magnetic)', 'category' => 'Office Equipment and Machinery', 'price' => [1000, 2500]],
        ['name' => 'Split AC Unit 18000 BTU', 'category' => 'Office Equipment and Machinery', 'price' => [9000, 16000]],
        ['name' => 'Refrigerator (Double Door)', 'category' => 'Office Equipment and Machinery', 'price' => [7000, 15000]],
        ['name' => 'Smart TV 55"', 'category' => 'Office Equipment and Machinery', 'price' => [8000, 17000]],
        ['name' => 'Photocopier Machine', 'category' => 'Office Equipment and Machinery', 'price' => [15000, 35000]],
        ['name' => 'Mixer Amplifier (DSPPA)', 'category' => 'Office Equipment and Machinery', 'price' => [4000, 9000]],
        ['name' => 'Wall Mounted Speaker', 'category' => 'Office Equipment and Machinery', 'price' => [1500, 3500]],
        ['name' => 'Toyota Hilux Pickup', 'category' => 'Pick up', 'price' => [350000, 550000]],
        ['name' => 'Suzuki Every Van', 'category' => 'Vans', 'price' => [200000, 320000]],
        ['name' => 'Yamaha Speed Boat', 'category' => 'Speed boats', 'price' => [400000, 900000]],
        ['name' => 'Honda Generator', 'category' => 'Other Equipment and Machinery', 'price' => [15000, 40000]],
        ['name' => 'Water Pump (Hitachi)', 'category' => 'Other Equipment and Machinery', 'price' => [3000, 7000]],
        ['name' => 'Weighing Scale (Digital)', 'category' => 'Other Equipment and Machinery', 'price' => [1500, 4000]],
        ['name' => 'Fire Extinguisher', 'category' => 'Other Equipment and Machinery', 'price' => [400, 1200]],
        ['name' => 'Lawn Mower', 'category' => 'Other Equipment and Machinery', 'price' => [4000, 9000]],
        ['name' => 'Makita Drill Set', 'category' => 'Tools', 'price' => [1500, 3500]],
        ['name' => 'Chainsaw (Makita)', 'category' => 'Tools', 'price' => [3000, 7000]],
        ['name' => 'Aluminium Ladder (9 Steps)', 'category' => 'Tools', 'price' => [1500, 3200]],
    ];

    private const VENDORS = [
        'Rainbow Tech Pvt Ltd', 'Maldives Business Solutions', 'Damas Hardware',
        'Buruzu Trading', 'STO Maldives', 'Villa Trade', 'Sino Maldives Pvt Ltd',
    ];

    public function run(): void
    {
        $buildings = $this->seedLocations();
        $rooms = AssetRoom::whereIn('building_id', $buildings->pluck('id'))->get();

        $categoryNames = collect(self::ITEM_POOL)->pluck('category')->unique();
        $categories = AssetCategory::whereIn('name', $categoryNames)->whereNotNull('asset_class_code')->get()->keyBy('name');

        $creator = User::first();
        $generator = app(AssetTagGenerator::class);

        for ($i = 0; $i < self::ASSET_COUNT; $i++) {
            $item = fake()->randomElement(self::ITEM_POOL);
            $category = $categories->get($item['category']);

            if (! $category) {
                continue; // seeded taxonomy changed/missing — skip rather than fail the whole run
            }

            $room = $rooms->random();
            $purchaseDate = Carbon::instance(fake()->dateTimeBetween('2019-01-01', 'now'));
            $status = fake()->randomElement([
                ...array_fill(0, 16, AssetStatus::InUse),
                ...array_fill(0, 2, AssetStatus::Damaged),
                AssetStatus::Auctioned,
                AssetStatus::Disposed,
                AssetStatus::Lost,
            ]);
            $assetType = fake()->boolean(85) ? 'purchased' : 'donated';

            // Mirrors the form's own validation: PO/voucher are
            // mandatory for a purchased asset, a donation reference is
            // mandatory for a donated one.
            $poNumber = null;
            $voucherNumber = null;
            $donationReferenceNo = null;
            if ($assetType === 'purchased') {
                $poSeq = fake()->numberBetween(1000, 1999);
                $pvSeq = str_pad((string) fake()->numberBetween(1, 200), 4, '0', STR_PAD_LEFT);
                $poNumber = "PO-{$poSeq}/J-GOM/{$purchaseDate->format('Y')}/{$pvSeq}";
                $voucherNumber = "PV-{$poSeq}/J-GOM/{$purchaseDate->format('Y')}/{$pvSeq}";
            } else {
                $donationReferenceNo = 'DON-'.$purchaseDate->format('Y').'-'.fake()->numberBetween(1, 999);
            }

            $numbers = $generator->generate($purchaseDate, $category->asset_class_code);

            Asset::create([
                'asset_tag' => $numbers['asset_tag'],
                'main_inventory_no' => $numbers['main_inventory_no'],
                'main_sequence' => $numbers['main_sequence'],
                'public_token' => $generator->newPublicToken(),
                'name' => $item['name'],
                'category_id' => $category->id,
                'room_id' => $room->id,
                'status' => $status->value,
                'purchase_date' => $purchaseDate,
                'purchase_price' => fake()->randomFloat(2, $item['price'][0], $item['price'][1]),
                'vendor' => fake()->randomElement(self::VENDORS),
                'po_number' => $poNumber,
                'voucher_number' => $voucherNumber,
                'donation_reference_no' => $donationReferenceNo,
                'fund_code' => $assetType === 'purchased' ? 'J-GOM' : null,
                'asset_type' => $assetType,
                'description' => fake()->boolean(40) ? fake()->sentence(8) : null,
                'created_by' => $creator->id,
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, AssetBuilding>
     */
    private function seedLocations(): \Illuminate\Support\Collection
    {
        $layout = [
            'Dhonfan Council Office' => ['Councilors room', 'Meeting room', 'Staff area 1', 'Staff area 2', 'Room 1', 'Room 2', 'Room 3'],
            'Store' => ['Store'],
            'Masjidhul Hidhayath' => ['Masjidhul Hidhayath'],
            'D Preschool' => ['D Preschool'],
            'Waste Management Center' => ['Waste Management Center', 'Waste Management Storage Area'],
            'Mehumaanee Ufa' => ['Mehumaanee Ufa', 'Staff Accommodation room 1'],
            'Old Health Post' => ['Old Health Post'],
        ];

        $buildings = collect();

        foreach ($layout as $buildingName => $roomNames) {
            $building = AssetBuilding::firstOrCreate(['name' => $buildingName], ['is_active' => true]);
            $buildings->push($building);

            foreach ($roomNames as $roomName) {
                AssetRoom::firstOrCreate(['building_id' => $building->id, 'name' => $roomName], ['is_active' => true]);
            }
        }

        return $buildings;
    }
}
