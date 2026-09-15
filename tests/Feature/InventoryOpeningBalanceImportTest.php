<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryRole;
use App\Filament\Inventory\Resources\Items\Imports\OpeningBalanceImporter;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Models\InventoryUnitOfMeasure;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryOpeningBalanceImportTest extends \Tests\TestCase
{
    use RefreshDatabase;

    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('inventory'));

        $category = InventoryItemCategory::create(['code' => 'STAT', 'name' => 'Stationery']);
        $uom = InventoryUnitOfMeasure::create(['code' => 'RM', 'name' => 'Ream', 'decimal_places' => 0]);
        $location = InventoryLocation::create(['code' => 'MAIN', 'name' => 'Main Store', 'is_default' => true]);
        $this->item = InventoryItem::create([
            'code' => 'STAT-0001', 'name' => 'A4 Copy Paper', 'category_id' => $category->id, 'uom_id' => $uom->id,
        ]);
        $this->item->stock()->create(['location_id' => $location->id, 'on_hand' => 0, 'reserved' => 0]);
    }

    private function makeStockAdmin(): User
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::StockAdmin]);

        return $user;
    }

    /**
     * Invoked directly (bypassing CSV parsing/file upload/queue),
     * matching how ItemImporter was verified in Phase 2 — isolates the
     * business logic this project owns from Filament's own,
     * already-tested plumbing.
     */
    private function import(User $admin, array $row): void
    {
        $import = Import::create([
            'file_name' => 'opening-balances.csv',
            'file_path' => 'imports/opening-balances.csv',
            'importer' => OpeningBalanceImporter::class,
            'total_rows' => 1,
            'user_id' => $admin->id,
        ]);

        $columns = collect(OpeningBalanceImporter::getColumns())
            ->mapWithKeys(fn ($column) => [$column->getName() => $column->getName()])
            ->all();

        (new OpeningBalanceImporter($import, $columns, []))($row);
    }

    public function test_importing_an_opening_balance_creates_an_opening_movement_and_sets_on_hand(): void
    {
        $admin = $this->makeStockAdmin();

        $this->import($admin, ['item_code' => 'STAT-0001', 'quantity' => '250', 'bin_location' => 'Rack A']);

        $this->item->refresh();
        $this->assertSame('Rack A', $this->item->bin_location);
        $this->assertSame('250.000', $this->item->stock()->sole()->on_hand);

        $movement = $this->item->movements()->sole();
        $this->assertSame(InventoryMovementType::Opening, $movement->movement_type);
        $this->assertSame('250.000', $movement->balance_after);
    }

    public function test_an_item_can_only_have_its_opening_balance_imported_once(): void
    {
        $admin = $this->makeStockAdmin();

        $this->import($admin, ['item_code' => 'STAT-0001', 'quantity' => '100', 'bin_location' => null]);

        // The real import job catches RowImportFailedException per row
        // and records it as a failed row rather than crashing the
        // batch (see ImportCsv::__invoke()) — invoking the importer
        // directly here bypasses that wrapper, so the test does its
        // own catch to confirm it's that same catchable exception type.
        try {
            $this->import($admin, ['item_code' => 'STAT-0001', 'quantity' => '999', 'bin_location' => null]);
            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertStringContainsString('already has an opening balance', $exception->getMessage());
        }

        $this->assertSame('100.000', $this->item->stock()->sole()->on_hand);
        $this->assertSame(1, $this->item->movements()->count());
    }

    public function test_an_unknown_item_code_does_not_crash_the_batch(): void
    {
        $admin = $this->makeStockAdmin();

        // No exception should escape — resolveRecord() throws
        // RowImportFailedException, which the real import job catches;
        // calling the importer directly here just confirms it's the
        // catchable exception type, not an uncaught one.
        try {
            $this->import($admin, ['item_code' => 'DOES-NOT-EXIST', 'quantity' => '10', 'bin_location' => null]);
            $this->fail('Expected RowImportFailedException was not thrown.');
        } catch (\Filament\Actions\Imports\Exceptions\RowImportFailedException $exception) {
            $this->assertStringContainsString('DOES-NOT-EXIST', $exception->getMessage());
        }
    }
}
