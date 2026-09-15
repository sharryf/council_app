<?php

namespace Tests\Feature;

use App\Enums\InventoryRole;
use App\Filament\Inventory\Resources\Items\Exports\ItemExporter;
use App\Filament\Inventory\Resources\Items\Imports\ItemImporter;
use App\Filament\Inventory\Resources\Items\ItemResource;
use App\Filament\Inventory\Resources\Items\Pages\CreateItem;
use App\Filament\Inventory\Resources\Items\Pages\ListItems;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Models\InventorySupplier;
use App\Models\InventoryUnitOfMeasure;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryItemTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItemCategory $category;

    private InventoryUnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('inventory'));

        $this->category = InventoryItemCategory::create(['code' => 'STAT', 'name' => 'Stationery']);
        $this->uom = InventoryUnitOfMeasure::create(['code' => 'PC', 'name' => 'Piece', 'decimal_places' => 0]);
        InventoryLocation::create(['code' => 'MAIN', 'name' => 'Main Store', 'is_default' => true]);
    }

    private function makeStockAdmin(): User
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::StockAdmin]);

        return $user;
    }

    public function test_creating_an_item_without_a_code_auto_generates_one_from_the_category(): void
    {
        InventoryLocation::create(['code' => 'ANNEX', 'name' => 'Annex Store']);

        $this->actingAs($this->makeStockAdmin());

        Livewire::test(CreateItem::class)
            ->fillForm([
                'name' => 'Ballpoint Pen Blue',
                'category_id' => $this->category->id,
                'uom_id' => $this->uom->id,
                'reorder_level' => 50,
                'reorder_qty' => 200,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = InventoryItem::sole();

        $this->assertSame('STAT-0001', $item->code);
        $this->assertSame(2, $item->stock()->count(), 'expected one stock row per active location');
        $this->assertTrue($item->stock()->get()->every(fn ($row) => (float) $row->on_hand === 0.0 && (float) $row->reserved === 0.0));
    }

    public function test_a_second_item_in_the_same_category_gets_the_next_sequence_number(): void
    {
        $this->actingAs($this->makeStockAdmin());

        Livewire::test(CreateItem::class)
            ->fillForm([
                'name' => 'First item',
                'category_id' => $this->category->id,
                'uom_id' => $this->uom->id,
                'reorder_level' => 0,
                'reorder_qty' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateItem::class)
            ->fillForm([
                'name' => 'Second item',
                'category_id' => $this->category->id,
                'uom_id' => $this->uom->id,
                'reorder_level' => 0,
                'reorder_qty' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('STAT-0001', InventoryItem::where('name', 'First item')->sole()->code);
        $this->assertSame('STAT-0002', InventoryItem::where('name', 'Second item')->sole()->code);
    }

    /**
     * BR-01: item code is unique. Case-insensitivity relies on MySQL's
     * default ci collation in production (see ItemForm's own comment);
     * this test submits matching case since Filament's ->unique() rule
     * (and SQLite, used in tests) compares before the code field's
     * uppercase dehydration runs.
     */
    public function test_a_duplicate_item_code_is_rejected(): void
    {
        $this->actingAs($this->makeStockAdmin());

        InventoryItem::create([
            'code' => 'STAT-0001', 'name' => 'Existing item', 'category_id' => $this->category->id, 'uom_id' => $this->uom->id,
        ]);

        Livewire::test(CreateItem::class)
            ->fillForm([
                'code' => 'STAT-0001',
                'name' => 'Duplicate code item',
                'category_id' => $this->category->id,
                'uom_id' => $this->uom->id,
                'reorder_level' => 0,
                'reorder_qty' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['code']);
    }

    /**
     * BR-06: max_level, if set, must exceed reorder_level.
     */
    public function test_max_level_must_exceed_reorder_level(): void
    {
        $this->actingAs($this->makeStockAdmin());

        Livewire::test(CreateItem::class)
            ->fillForm([
                'name' => 'Bad reorder settings',
                'category_id' => $this->category->id,
                'uom_id' => $this->uom->id,
                'reorder_level' => 50,
                'reorder_qty' => 0,
                'max_level' => 10,
            ])
            ->call('create')
            ->assertHasFormErrors(['max_level']);
    }

    public function test_a_plain_user_role_cannot_create_items_but_can_view_them(): void
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::User]);

        $this->assertFalse(ItemResource::canCreate());

        $this->actingAs($user)
            ->get('/inventory/items')
            ->assertOk();

        $this->actingAs($user)
            ->get('/inventory/items/create')
            ->assertForbidden();
    }

    public function test_a_stock_admin_can_create_edit_and_delete_items(): void
    {
        $this->actingAs($this->makeStockAdmin());

        $this->assertTrue(ItemResource::canCreate());

        $item = InventoryItem::create([
            'code' => 'STAT-0001', 'name' => 'Editable item', 'category_id' => $this->category->id, 'uom_id' => $this->uom->id,
        ]);

        $this->assertTrue(ItemResource::canEdit($item));
        $this->assertTrue(ItemResource::canDelete($item));
    }

    public function test_the_item_list_is_searchable_by_name_brand_and_description(): void
    {
        $this->actingAs($this->makeStockAdmin());

        InventoryItem::create([
            'code' => 'STAT-0001', 'name' => 'A4 Copy Paper 80gsm', 'brand' => 'PaperCo',
            'category_id' => $this->category->id, 'uom_id' => $this->uom->id,
        ]);
        InventoryItem::create([
            'code' => 'STAT-0002', 'name' => 'Stapler Medium', 'brand' => 'ClipRight',
            'category_id' => $this->category->id, 'uom_id' => $this->uom->id,
        ]);

        Livewire::test(ListItems::class)
            ->searchTable('PaperCo')
            ->assertCanSeeTableRecords(InventoryItem::where('brand', 'PaperCo')->get())
            ->assertCanNotSeeTableRecords(InventoryItem::where('brand', 'ClipRight')->get());
    }

    public function test_default_supplier_can_be_assigned(): void
    {
        $this->actingAs($this->makeStockAdmin());

        $supplier = InventorySupplier::create(['code' => 'SUP1', 'name' => 'Test Supplier']);

        Livewire::test(CreateItem::class)
            ->fillForm([
                'name' => 'Item with supplier',
                'category_id' => $this->category->id,
                'uom_id' => $this->uom->id,
                'default_supplier_id' => $supplier->id,
                'reorder_level' => 0,
                'reorder_qty' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($supplier->id, InventoryItem::sole()->default_supplier_id);
    }

    /**
     * Invokes ItemImporter directly with a data array shaped like one
     * parsed CSV row, bypassing the file-upload/CSV-parsing/queue
     * machinery (Filament's own, not ours) to isolate what this
     * project's code actually owns: the column list, resolveRecord()'s
     * upsert-by-code, and afterCreate()'s stock-row bootstrap — mirrors
     * the CSV round-trip a Stock Admin would perform via ItemExporter's
     * own columns.
     */
    public function test_importing_a_csv_row_resolves_category_and_uom_by_code_and_creates_stock_rows(): void
    {
        $admin = $this->makeStockAdmin();
        $this->actingAs($admin);

        InventoryLocation::create(['code' => 'ANNEX', 'name' => 'Annex Store']);

        $import = Import::create([
            'file_name' => 'items.csv',
            'file_path' => 'imports/items.csv',
            'importer' => ItemImporter::class,
            'total_rows' => 1,
            'user_id' => $admin->id,
        ]);

        $columns = collect(ItemImporter::getColumns())->mapWithKeys(fn ($column) => [$column->getName() => $column->getName()])->all();

        $importer = new ItemImporter($import, $columns, []);
        $importer([
            'code' => 'ELEC-0001',
            'name' => 'LED Bulb 9W E27',
            'description' => '',
            'category' => $this->makeCategory('ELEC', 'Electrical'),
            'uom' => 'PC',
            'brand' => '',
            'model_spec' => '',
            'bin_location' => '',
            'reorder_level' => '20',
            'reorder_qty' => '50',
            'max_level' => '',
            'lead_time_days' => '',
            'supplier' => '',
            'is_stock_tracked' => '1',
            'is_active' => '1',
        ]);

        $item = InventoryItem::where('code', 'ELEC-0001')->sole();

        $this->assertSame('LED Bulb 9W E27', $item->name);
        $this->assertSame('ELEC', $item->category->code);
        $this->assertSame('PC', $item->uom->code);
        $this->assertSame(2, $item->stock()->count(), 'expected one stock row per active location, same as the form-created flow');
    }

    private function makeCategory(string $code, string $name): string
    {
        InventoryItemCategory::query()->firstOrCreate(['code' => $code], ['name' => $name]);

        return $code;
    }
}
