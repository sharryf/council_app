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
 * re-run, and picks up any code/name corrections without duplicating
 * rows.
 */
class AssetCategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            ['gl_code' => '421001', 'name' => 'Land', 'children' => [
                ['name' => 'Land - Agricultural', 'code' => 'Z001'],
                ['name' => 'Land - Airports', 'code' => 'Z002'],
                ['name' => 'Land - Cemetery', 'code' => 'Z003'],
                ['name' => 'Land - Colleges', 'code' => 'Z004'],
                ['name' => 'Land - Conventional Centres', 'code' => 'Z005'],
                ['name' => 'Land - Flats', 'code' => 'Z006'],
                ['name' => 'Land - Hospitals', 'code' => 'Z007'],
                ['name' => 'Land - Hotels', 'code' => 'Z008'],
                ['name' => 'Land - Industrial', 'code' => 'Z009'],
                ['name' => 'Land - Military', 'code' => 'Z010'],
                ['name' => 'Land - Mosques', 'code' => 'Z011'],
                ['name' => 'Land - Offices', 'code' => 'Z012'],
                ['name' => 'Land - Parks', 'code' => 'Z013'],
                ['name' => 'Land - Prisons', 'code' => 'Z014'],
                ['name' => 'Land - Private Schools', 'code' => 'Z015'],
                ['name' => 'Land - Public Schools', 'code' => 'Z016'],
                ['name' => 'Land - Reclaimed Areas', 'code' => 'Z017'],
                ['name' => 'Land - Residential Area', 'code' => 'Z018'],
                ['name' => 'Land - Resorts', 'code' => 'Z019'],
                ['name' => 'Land - Restaurants and Café', 'code' => 'Z020'],
                ['name' => 'Land - Roads', 'code' => 'Z021'],
                ['name' => 'Land - Shops', 'code' => 'Z022'],
                ['name' => 'Land - Sport Grounds', 'code' => 'Z023'],
                ['name' => 'Land - Stadium', 'code' => 'Z024'],
                ['name' => 'Land - Uninhabited islands', 'code' => 'Z025'],
                ['name' => 'Land - Others', 'code' => 'Z099'],
            ]],
            ['gl_code' => '421002', 'name' => 'Residential Buildings', 'children' => [
                ['name' => 'Buildings - Flats', 'code' => 'Z100'],
                ['name' => 'Other Residential Buildings', 'code' => 'Z124'],
            ]],
            ['gl_code' => '421003', 'name' => 'Non-Residential Buildings', 'children' => [
                ['name' => 'Buildings - Colleges', 'code' => 'Z125'],
                ['name' => 'Buildings - Convention Centres', 'code' => 'Z126'],
                ['name' => 'Buildings - Factories', 'code' => 'Z127'],
                ['name' => 'Buildings - Hospitals', 'code' => 'Z128'],
                ['name' => 'Buildings - Military Buildings', 'code' => 'Z129'],
                ['name' => 'Buildings - Mosques', 'code' => 'Z130'],
                ['name' => 'Buildings - Offices', 'code' => 'Z131'],
                ['name' => 'Buildings - Prisons', 'code' => 'Z132'],
                ['name' => 'Buildings - Schools', 'code' => 'Z133'],
                ['name' => 'Other Non Residential Buildings', 'code' => 'Z224'],
            ]],
            ['gl_code' => '422001', 'name' => 'Roads and Bridges', 'children' => [
                ['name' => 'Bridges', 'code' => 'Z325'],
                ['name' => 'Roads', 'code' => 'Z326'],
            ]],
            ['gl_code' => '422002', 'name' => 'Airports', 'children' => [
                ['name' => 'Apron', 'code' => 'Z225'],
                ['name' => 'Oil Tanks', 'code' => 'Z226'],
                ['name' => 'Runway', 'code' => 'Z227'],
                ['name' => 'Terminal Buildings', 'code' => 'Z228'],
                ['name' => 'Other Airport Buildings', 'code' => 'Z249'],
            ]],
            ['gl_code' => '422003', 'name' => 'Wharves, Ports and Harbours', 'children' => [
                ['name' => 'Harbors', 'code' => 'Z250'],
                ['name' => 'Ports', 'code' => 'Z251'],
                ['name' => 'Wharves', 'code' => 'Z252'],
            ]],
            ['gl_code' => '422004', 'name' => 'Water & Sanitation Systems', 'children' => [
                ['name' => 'Water & Sanitation Systems', 'code' => 'Z275'],
            ]],
            ['gl_code' => '422005', 'name' => 'Electricity Systems', 'children' => [
                ['name' => 'Electricity Reticulation Systems', 'code' => 'Z300'],
            ]],
            ['gl_code' => '422999', 'name' => 'Other Infrastructure', 'children' => [
                ['name' => 'Cemetery', 'code' => 'Z350'],
                ['name' => 'Monuments', 'code' => 'Z351'],
                ['name' => 'Museums', 'code' => 'Z352'],
                ['name' => 'Parks', 'code' => 'Z353'],
                ['name' => 'Sports Complex', 'code' => 'Z354'],
                ['name' => 'Stadium', 'code' => 'Z355'],
                ['name' => 'Other infrastructure', 'code' => 'Z399'],
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
            ['gl_code' => '423006', 'name' => 'Communication Infrastructure', 'children' => [
                ['name' => 'Network', 'code' => 'Z625'],
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
            ['gl_code' => '424003', 'name' => 'Aerospace equipment', 'children' => [
                ['name' => 'Aeroplanes', 'code' => 'Z825'],
                ['name' => 'Helicopters', 'code' => 'Z826'],
                ['name' => 'Jets', 'code' => 'Z827'],
                ['name' => 'Sea Planes', 'code' => 'Z828'],
                ['name' => 'Other Aerospace Equipment', 'code' => 'Z849'],
            ]],
        ];

        foreach ($tree as $top) {
            $parent = AssetCategory::query()->updateOrCreate(
                ['parent_id' => null, 'name' => $top['name']],
                ['gl_code' => $top['gl_code'], 'is_active' => true],
            );

            foreach ($top['children'] as $child) {
                AssetCategory::query()->updateOrCreate(
                    ['parent_id' => $parent->id, 'name' => $child['name']],
                    ['asset_class_code' => $child['code'], 'is_active' => true],
                );
            }
        }
    }
}
