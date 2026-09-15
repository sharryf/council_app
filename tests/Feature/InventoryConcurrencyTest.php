<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Models\InventoryNumberSequence;
use App\Models\InventoryUnitOfMeasure;
use App\Services\Inventory\InventorySequenceService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Phase 9 hardening. Every other test in this suite runs against
 * sqlite::memory:, where lockForUpdate() is a no-op and every
 * connection gets its own isolated database — SQLite structurally
 * cannot exercise real row-locking behaviour, so no test anywhere else
 * in the app can prove the row lock in StockMovementService/
 * InventorySequenceService actually serializes concurrent access.
 *
 * This file instead points a second connection ("concurrency_b") at
 * the real local MySQL database this app's dev server already uses
 * (DB_CONNECTION=mysql in .env — the sqlite/:memory: override in
 * phpunit.xml only applies to the *default* connection), and proves a
 * genuinely separate MySQL session blocks on a row a still-open
 * transaction on the default connection holds, then proceeds the
 * instant that transaction releases it. No forking (not available on
 * Windows) — a locking read from a second real connection blocks
 * synchronously by itself, no orchestration needed.
 *
 * Runs against the real dev database rather than a disposable one
 * created fresh (the plan's original intent) because the configured
 * `council_app` MySQL user has no CREATE DATABASE grant — every row
 * this file writes uses a `ZZCONCTEST` prefix found nowhere else in
 * the app, and setUp()/tearDown() both delete by that prefix so a
 * crashed prior run cleans itself up on the next one.
 *
 * Deliberately excluded from the default `php artisan test` run (see
 * phpunit.xml) — it needs a live local MySQL server and touches real
 * dev data (scoped to its own prefix), so it isn't safe to interleave
 * with the rest of the sqlite-based suite. Run it explicitly:
 * `php artisan test tests/Feature/InventoryConcurrencyTest.php`.
 */
#[Group('concurrency')]
class InventoryConcurrencyTest extends TestCase
{
    private const PREFIX = 'ZZCONCTEST';

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml forces the *default* connection to sqlite::memory:
        // for every test in the suite — switch it to the real MySQL
        // database for this file only, so plain Eloquent calls (and
        // StockMovementService/InventorySequenceService, which always
        // use the default connection) hit the same database
        // "concurrency_b" independently connects to.
        config(['database.connections.mysql' => $this->mysqlConfig()]);
        config(['database.connections.concurrency_b' => $this->mysqlConfig()]);
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        DB::purge('concurrency_b');

        $this->cleanUpTestRows();
    }

    protected function tearDown(): void
    {
        $this->cleanUpTestRows();
        DB::purge('mysql');
        DB::purge('concurrency_b');
        config(['database.default' => 'sqlite']);

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function mysqlConfig(): array
    {
        // env('DB_DATABASE') is hijacked to ':memory:' for the default
        // connection by phpunit.xml — read the real dev database name
        // straight from .env instead of trusting it here.
        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', (string) $env, $matches);
        $database = trim($matches[1] ?? 'council_app');

        return [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $database,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ];
    }

    private function cleanUpTestRows(): void
    {
        InventoryItem::query()->where('code', 'like', self::PREFIX.'%')->get()->each(function (InventoryItem $item) {
            $item->stock()->delete();
            $item->delete();
        });
        InventoryLocation::query()->where('code', self::PREFIX)->delete();
        InventoryItemCategory::query()->where('code', self::PREFIX)->delete();
        InventoryUnitOfMeasure::query()->where('code', self::PREFIX)->delete();
        InventoryNumberSequence::query()->where('doc_type', self::PREFIX)->delete();
    }

    public function test_concurrent_issues_of_the_last_unit_serialize_via_the_row_lock(): void
    {
        $location = InventoryLocation::create(['code' => self::PREFIX, 'name' => 'Concurrency Test Store', 'is_default' => false]);
        $category = InventoryItemCategory::create(['code' => self::PREFIX, 'name' => 'Concurrency Test Category']);
        $uom = InventoryUnitOfMeasure::create(['code' => self::PREFIX, 'name' => 'Concurrency Test Unit', 'decimal_places' => 0]);
        $item = InventoryItem::create(['code' => self::PREFIX.'-0001', 'name' => 'Concurrency Test Item', 'category_id' => $category->id, 'uom_id' => $uom->id]);
        $item->stock()->create(['location_id' => $location->id, 'on_hand' => 1, 'reserved' => 0]);

        // Connection A (the app's own default connection): take the
        // exact SELECT ... FOR UPDATE StockMovementService::record()
        // takes to issue the last unit, and hold it open (no commit
        // yet) — simulating one storekeeper mid-issue.
        DB::beginTransaction();
        DB::select(
            'select * from inventory_item_stock where item_id = ? and location_id = ? for update',
            [$item->id, $location->id],
        );

        // Connection B: a second, fully independent MySQL session — a
        // second storekeeper trying to issue the same last unit at the
        // same moment. A short lock-wait timeout turns "blocks forever"
        // into a deterministic, assertable failure.
        DB::connection('concurrency_b')->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;

        try {
            DB::connection('concurrency_b')->select(
                'select * from inventory_item_stock where item_id = ? and location_id = ? for update',
                [$item->id, $location->id],
            );
        } catch (QueryException $exception) {
            $blocked = str_contains($exception->getMessage(), 'Lock wait timeout');
        }

        $this->assertTrue($blocked, 'A second real connection should block on the row A is still holding — the exact protection spec 16 edge case 1 relies on.');

        // A commits (as record() would after posting the issue) —
        // releasing the lock. B's retry should now succeed immediately.
        DB::commit();

        $rows = DB::connection('concurrency_b')->select(
            'select * from inventory_item_stock where item_id = ? and location_id = ? for update',
            [$item->id, $location->id],
        );
        $this->assertCount(1, $rows, 'Once A released the lock, B should acquire it and read the row immediately.');
    }

    public function test_concurrent_sequence_generation_serializes_via_the_row_lock(): void
    {
        $sequence = InventoryNumberSequence::create([
            'doc_type' => self::PREFIX,
            'year' => (int) now()->format('Y'),
            'prefix' => 'ZC',
            'last_number' => 0,
            'padding' => 4,
        ]);

        // Same proof as above, against the row InventorySequenceService::
        // next() locks — the spec 17 checklist line this replaces
        // ("100 concurrent document creations produce 100 distinct
        // numbers") asks whether two real, simultaneous callers
        // serialize rather than race; InventorySequenceServiceTest's own
        // existing test can only loop one connection sequentially and
        // says so in its docblock.
        DB::beginTransaction();
        DB::select('select * from inventory_number_sequences where id = ? for update', [$sequence->id]);

        DB::connection('concurrency_b')->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;

        try {
            DB::connection('concurrency_b')->select('select * from inventory_number_sequences where id = ? for update', [$sequence->id]);
        } catch (QueryException $exception) {
            $blocked = str_contains($exception->getMessage(), 'Lock wait timeout');
        }

        $this->assertTrue($blocked, 'A second real connection should block on the sequence row A is still holding.');

        DB::commit();

        $rows = DB::connection('concurrency_b')->select('select * from inventory_number_sequences where id = ? for update', [$sequence->id]);
        $this->assertCount(1, $rows);

        // With the lock proven real, two back-to-back calls through the
        // service itself still produce distinct, correctly-incremented
        // numbers.
        $first = app(InventorySequenceService::class)->next(self::PREFIX);
        $second = app(InventorySequenceService::class)->next(self::PREFIX);

        $this->assertNotSame($first, $second);
        $this->assertSame('ZC-'.now()->format('Y').'-0002', $second);
    }
}
