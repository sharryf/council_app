<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the official government asset classification (GL code, category,
 * sub-category, and asset class code) supplied in
 * "Documents/Asset Module/Pages from Asset Classes.pdf" — every asset's
 * category should ultimately map onto one of these class codes rather
 * than a locally invented name, so this ships as the default tree
 * instead of leaving councils to type ~150 categories by hand.
 *
 * updateOrCreate() throughout, matching InventorySeeder — safe to
 * re-run. Children are keyed by asset_class_code (not name) precisely
 * so a name correction here (e.g. "Land - Agricultural" → the current
 * "Agricultural", once the parent already reads "Land") updates the
 * existing row in place on re-seed instead of leaving the old-named
 * row behind as an orphaned duplicate — the class code is the one
 * value guaranteed not to change.
 *
 * Trimmed on request to only the categories this council actually
 * registers assets under — Land, Roads and Bridges, Airports, Wharves/
 * Ports/Harbours, Water & Sanitation Systems, Electricity Systems,
 * Communication Infrastructure, and Aerospace equipment (plus all of
 * their sub-categories) are deliberately left out of the source PDF's
 * full classification. Since this only ever adds/updates rows, an
 * install seeded before this trim keeps the old ones around until
 * they're removed by hand (or re-seeded fresh).
 */
class AssetCategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            ['gl_code' => '421002', 'name' => 'Residential Buildings', 'children' => [
                ['name' => 'Flats', 'code' => 'Z100'],
                ['name' => 'Other Buildings', 'code' => 'Z124'],
            ]],
            ['gl_code' => '421003', 'name' => 'Non-Residential Buildings', 'children' => [
                ['name' => 'Colleges', 'code' => 'Z125'],
                ['name' => 'Convention Centres', 'code' => 'Z126'],
                ['name' => 'Factories', 'code' => 'Z127'],
                ['name' => 'Hospitals', 'code' => 'Z128'],
                ['name' => 'Military Buildings', 'code' => 'Z129'],
                ['name' => 'Mosques', 'code' => 'Z130'],
                ['name' => 'Offices', 'code' => 'Z131'],
                ['name' => 'Prisons', 'code' => 'Z132'],
                ['name' => 'Schools', 'code' => 'Z133'],
                ['name' => 'Other Buildings', 'code' => 'Z224'],
            ]],
            ['gl_code' => '422999', 'name' => 'Other Infrastructure', 'children' => [
                ['name' => 'Cemetery', 'code' => 'Z350'],
                ['name' => 'Monuments', 'code' => 'Z351'],
                ['name' => 'Museums', 'code' => 'Z352'],
                ['name' => 'Parks', 'code' => 'Z353'],
                ['name' => 'Sports Complex', 'code' => 'Z354'],
                ['name' => 'Stadium', 'code' => 'Z355'],
                ['name' => 'Other', 'code' => 'Z399'],
            ]],
            ['gl_code' => '423001', 'name' => 'Furniture & Fittings', 'children' => [
                ['name' => 'College Furniture', 'code' => 'Z650'],
                ['name' => 'Hospital and Health Center Furniture', 'code' => 'Z651'],
                ['name' => 'Office Furniture', 'code' => 'Z652'],
                ['name' => 'School Furniture', 'code' => 'Z653'],
                ['name' => 'Household Furniture', 'code' => 'Z654'],
                ['name' => 'Other Fittings', 'code' => 'Z655'],
                ['name' => 'Other Furniture', 'code' => 'Z699'],
            ]],
            ['gl_code' => '423002', 'name' => 'Machinery and Equipment', 'children' => [
                ['name' => 'Hospital Equipment and Machinery', 'code' => 'Z700'],
                ['name' => 'Laboratory Equipment and Machinery', 'code' => 'Z701'],
                ['name' => 'Office Equipment and Machinery', 'code' => 'Z702'],
            ]],
            ['gl_code' => '423003', 'name' => 'Vehicular Equipment', 'children' => [
                ['name' => 'Barge', 'code' => 'Z450'],
                ['name' => 'Cranes', 'code' => 'Z451'],
                ['name' => 'Dredgers', 'code' => 'Z452'],
                ['name' => 'Excavators', 'code' => 'Z453'],
                ['name' => 'Fork lifts', 'code' => 'Z454'],
                ['name' => 'Loaders', 'code' => 'Z455'],
                ['name' => 'Other Vehicular Equipment', 'code' => 'Z499'],
            ]],
            ['gl_code' => '423004', 'name' => 'Tools, Instruments, Apparatus', 'children' => [
                ['name' => 'Apparatus', 'code' => 'Z775'],
                ['name' => 'Instruments', 'code' => 'Z776'],
                ['name' => 'Tools', 'code' => 'Z777'],
            ]],
            ['gl_code' => '423005', 'name' => 'Reference Books & Exhibition Goods', 'children' => [
                ['name' => 'Books', 'code' => 'Z800'],
                ['name' => 'Exhibition Goods', 'code' => 'Z801'],
            ]],
            ['gl_code' => '423007', 'name' => 'Computer Software', 'children' => [
                ['name' => 'Software', 'code' => 'Z600'],
            ]],
            ['gl_code' => '423008', 'name' => 'IT-Related Hardware', 'children' => [
                ['name' => 'Computers', 'code' => 'Z550'],
                ['name' => 'Printers', 'code' => 'Z551'],
                ['name' => 'Projectors', 'code' => 'Z552'],
                ['name' => 'Routers', 'code' => 'Z553'],
                ['name' => 'Scanners', 'code' => 'Z554'],
                ['name' => 'Servers', 'code' => 'Z555'],
                ['name' => 'Switches', 'code' => 'Z556'],
                ['name' => 'Other IT Equipment', 'code' => 'Z599'],
            ]],
            ['gl_code' => '423999', 'name' => 'Other Equipment', 'children' => [
                ['name' => 'Other Equipment and Machinery', 'code' => 'Z750'],
            ]],
            ['gl_code' => '424001', 'name' => 'Motor Vehicles', 'children' => [
                ['name' => 'Buses', 'code' => 'Z400'],
                ['name' => 'Cars', 'code' => 'Z401'],
                ['name' => 'Lorry', 'code' => 'Z402'],
                ['name' => 'Military Vehicles', 'code' => 'Z403'],
                ['name' => 'Motor cycles', 'code' => 'Z404'],
                ['name' => 'Pick up', 'code' => 'Z405'],
                ['name' => 'Trucks', 'code' => 'Z406'],
                ['name' => 'Vans', 'code' => 'Z407'],
                ['name' => 'Other Motor Vehicles', 'code' => 'Z449'],
            ]],
            ['gl_code' => '424002', 'name' => 'Ships and Boats', 'children' => [
                ['name' => 'Fishing Vessels', 'code' => 'Z500'],
                ['name' => 'Goods carrier Dhoni', 'code' => 'Z501'],
                ['name' => 'Passenger carrier Dhoni', 'code' => 'Z502'],
                ['name' => 'Ships', 'code' => 'Z503'],
                ['name' => 'Speed boats', 'code' => 'Z504'],
                ['name' => 'Other Vessels', 'code' => 'Z549'],
            ]],
        ];

        foreach ($tree as $top) {
            $parent = AssetCategory::query()->updateOrCreate(
                ['parent_id' => null, 'name' => $top['name']],
                ['gl_code' => $top['gl_code'], 'is_active' => true],
            );

            foreach ($top['children'] as $child) {
                AssetCategory::query()->updateOrCreate(
                    ['asset_class_code' => $child['code']],
                    ['parent_id' => $parent->id, 'name' => $child['name'], 'is_active' => true],
                );
            }
        }
    }
}
