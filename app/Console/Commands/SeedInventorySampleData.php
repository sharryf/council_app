<?php

namespace App\Console\Commands;

use App\Enums\InventoryAdjustmentStatus;
use App\Enums\InventoryAdjustmentType;
use App\Enums\InventoryGoodsReceiptStatus;
use App\Enums\InventoryIssueRequestLineStatus;
use App\Enums\InventoryIssueRequestStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryPriority;
use App\Enums\InventoryReturnCondition;
use App\Enums\InventoryReturnStatus;
use App\Enums\InventorySourceType;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryIssueRequest;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Models\InventoryRecipient;
use App\Models\InventoryStockAdjustment;
use App\Models\InventoryStockReturn;
use App\Models\InventorySupplier;
use App\Models\InventoryUnitOfMeasure;
use App\Models\User;
use App\Services\Inventory\InventorySequenceService;
use App\Services\Inventory\ItemCreationService;
use App\Services\Inventory\StockMovementService;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dev-only convenience for manual testing — 20 realistic items across
 * the app's existing categories, each with at least one Receipt and one
 * Issue (the two everyday movement types), plus a small, deliberately
 * sparse handful of Adjustments and Returns on only a few items, the
 * same "most things are In/Out, corrections are the exception" shape a
 * real store room has. Purely additive: never touches existing items
 * or documents, and every document goes through the same
 * StockMovementService/sequence-number path the real UI uses, so the
 * result is indistinguishable from data a person clicked through by
 * hand.
 */
class SeedInventorySampleData extends Command
{
    protected $signature = 'inventory:seed-sample-data';

    protected $description = 'Creates 20 sample items with realistic receipt/issue history for manual testing.';

    /**
     * name, category code, uom code, reorder level, reorder qty — the
     * codes must match InventoryItemCategory/InventoryUnitOfMeasure
     * exactly as InventorySeeder creates them (STAT/ELEC/CLEAN/IT/
     * PANTRY/SAFETY/OTHER and PC/BOX/PKT/RM/DOZ/M/ROLL/SET/L/KG/BTL/
     * PAIR) — anything else silently skips the item (see the "Skipping"
     * warning below) and throws off every batch index that follows.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: int, 4: int}>
     */
    private const CATALOG = [
        ['Ballpoint Pen Blue', 'STAT', 'PC', 50, 200],
        ['Whiteboard Marker Black', 'STAT', 'PC', 20, 60],
        ['Stapler Medium', 'STAT', 'PC', 3, 10],
        ['Sticky Notes Pad', 'STAT', 'PKT', 20, 60],
        ['File Folder A4', 'STAT', 'PC', 25, 100],
        ['First Aid Kit', 'SAFETY', 'SET', 2, 5],
        ['LED Bulb 9W E27', 'ELEC', 'PC', 20, 50],
        ['Extension Cord 5m', 'ELEC', 'PC', 3, 10],
        ['Cable 2.5mm 3-core', 'ELEC', 'M', 50, 200],
        ['Switch Socket 13A', 'ELEC', 'PC', 10, 25],
        ['AA Batteries Pack of 4', 'ELEC', 'PKT', 15, 40],
        ['Floor Cleaner 5L', 'CLEAN', 'BTL', 5, 12],
        ['Hand Soap Refill 500ml', 'CLEAN', 'BTL', 10, 24],
        ['Toilet Paper Roll', 'CLEAN', 'ROLL', 40, 100],
        ['Garbage Bag Large', 'CLEAN', 'PKT', 10, 30],
        ['Microfibre Cloth', 'CLEAN', 'PC', 10, 25],
        ['USB Flash Drive 32GB', 'IT', 'PC', 5, 15],
        ['Printer Toner Cartridge Black', 'IT', 'PC', 3, 10],
        ['HDMI Cable 2m', 'IT', 'PC', 5, 15],
        ['Highlighter Set 4-Colour', 'STAT', 'SET', 10, 30],
    ];

    /** Which item indices (0-based) land on which GRN — the bulk "in" movements. */
    private const GRN_BATCHES = [
        [0, 1, 2, 3],
        [4, 5, 6, 7],
        [8, 9, 10, 11],
        [12, 13, 14, 15],
        [16, 17, 18, 19],
    ];

    /** Which item indices land on which issue request — the bulk "out" movements. */
    private const ISSUE_BATCHES = [
        [0, 1, 2],
        [3, 4],
        [5, 6, 7],
        [8, 9],
        [10, 11, 12],
        [13, 14],
        [15, 16, 17],
        [18, 19],
        [0, 8], // a couple of items get a second, smaller issue
    ];

    /** Sparse "other" types — only these few items get one, per the request. */
    private const STOCK_TAKE_ITEMS = [1, 6, 11, 16];

    private const RETURN_ITEMS = [2, 8, 15];

    /** Sample non-login people goods get issued to, for the new recipient field. */
    private const RECIPIENTS = [
        ['Aishath Shifa', 'Council Office'],
        ['Mohamed Nazim', 'Health Centre'],
        ['Fathimath Reesha', 'School'],
        ['Hussain Adnan', 'Harbour Office'],
    ];

    public function handle(
        ItemCreationService $itemCreation,
        StockMovementService $movements,
        InventorySequenceService $sequences,
    ): int {
        // A console command has no "current panel" the way an HTTP
        // request does — without this, any code path that builds a
        // Filament resource URL (e.g. a reorder-severity notification
        // fired from inside StockMovementService::record()) resolves
        // against the wrong panel and throws a route-not-found error.
        Filament::setCurrentPanel(Filament::getPanel('inventory'));

        $location = InventoryLocation::query()->where('is_default', true)->firstOrFail();
        $supplier = InventorySupplier::query()->firstOrFail();

        $admin = User::query()->where('email', 'admin@council.test')->firstOrFail();
        $stockAdmin = User::query()->find(4) ?? $admin; // Aminath Rafaah
        $approver = User::query()->find(3) ?? $admin; // Abdulla Ahsan
        $requesterPool = User::query()->whereIn('id', [2, 9, 10, 11, 13, 15, 17, 19, 21])->get();

        if ($requesterPool->isEmpty()) {
            $requesterPool = collect([$admin]);
        }

        $recipients = collect(self::RECIPIENTS)->map(
            fn (array $recipient) => InventoryRecipient::query()->firstOrCreate(
                ['name' => $recipient[0]],
                ['department' => $recipient[1]],
            ),
        );

        $categories = InventoryItemCategory::query()->get()->keyBy('code');
        $uoms = InventoryUnitOfMeasure::query()->get()->keyBy('code');

        // Keyed by its position in CATALOG (not re-indexed on skip) — every
        // batch constant below (GRN_BATCHES, ISSUE_BATCHES, ...) refers to
        // items by that fixed position, so a skipped item must leave a gap
        // rather than shift every later index onto the wrong item.
        $items = [];

        foreach (self::CATALOG as $index => [$name, $categoryCode, $uomCode, $reorderLevel, $reorderQty]) {
            $category = $categories->get($categoryCode);
            $uom = $uoms->get($uomCode);

            if (! $category || ! $uom) {
                $this->error("Skipping {$name}: category {$categoryCode} or UoM {$uomCode} not found.");

                continue;
            }

            $items[$index] = $itemCreation->create([
                'name' => $name,
                'category_id' => $category->id,
                'uom_id' => $uom->id,
                'reorder_level' => $reorderLevel,
                'reorder_qty' => $reorderQty,
                'created_by' => $admin->id,
            ]);
        }

        if (count($items) !== count(self::CATALOG)) {
            $this->error('Some catalog items were skipped (see above) — fix CATALOG\'s category/UoM codes before continuing, or the batches below will reference the wrong items.');

            return self::FAILURE;
        }

        $this->info(count($items).' items created.');

        $receiptQtyByItem = [];

        foreach (self::GRN_BATCHES as $batch) {
            $lines = collect($batch)->map(function (int $index) use ($items, &$receiptQtyByItem) {
                $item = $items[$index];
                $qty = $this->roundForUom($item, (float) $item->reorder_qty * $this->randomFloat(1.0, 1.5));
                $receiptQtyByItem[$index] = $qty;

                return ['item' => $item, 'quantity' => $qty];
            });

            $this->postGoodsReceipt($lines, $location, $supplier, $stockAdmin, $sequences, $movements);
        }

        $this->info(count(self::GRN_BATCHES).' goods receipts posted.');

        // A couple of items appear in two issue batches (see
        // ISSUE_BATCHES's trailing [0, 8] entry) — track what's actually
        // still available as batches consume it, not just each item's
        // original receipt quantity, or a second issue can ask for more
        // than is left.
        $remainingByItem = $receiptQtyByItem;
        $issuedLineByItem = [];

        foreach (self::ISSUE_BATCHES as $batch) {
            $requester = $requesterPool->random();
            $lines = collect($batch)->map(function (int $index) use ($items, &$remainingByItem) {
                $item = $items[$index];
                $available = (string) ($remainingByItem[$index] ?? '0');
                $qty = $this->roundForUom($item, (float) $available * $this->randomFloat(0.3, 0.8));

                if (bccomp($qty, $available, 3) > 0) {
                    $qty = $available;
                }

                $remainingByItem[$index] = bcsub($available, $qty, 3);

                return ['item' => $item, 'index' => $index, 'quantity' => $qty];
            });

            $issueLine = $this->createSubmitApproveIssue($lines, $location, $requester, $recipients->random(), $approver, $stockAdmin, $sequences, $movements);
            $issuedLineByItem += $issueLine;
        }

        $this->info(count(self::ISSUE_BATCHES).' issue requests submitted, approved, and issued.');

        foreach (self::STOCK_TAKE_ITEMS as $index) {
            $this->postStockTakeAdjustment($items[$index], $location, $stockAdmin, $sequences, $movements);
        }

        $this->info(count(self::STOCK_TAKE_ITEMS).' stock-take adjustments posted.');

        $returnCount = 0;

        foreach (self::RETURN_ITEMS as $index) {
            if (! isset($issuedLineByItem[$index])) {
                continue;
            }

            $this->postReturn($issuedLineByItem[$index], $location, $stockAdmin, $sequences, $movements);
            $returnCount++;
        }

        $this->info("{$returnCount} returns posted.");

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * A handful of existing rows in this dev database (e.g. a
     * dashboard-demo fixture with request_no "IR-2026-9999") have
     * document numbers that were hardcoded directly rather than
     * generated through InventorySequenceService::next() — so next()
     * can legitimately hand back a number that collides with one of
     * those. Rather than guess which historical numbers are "real" vs.
     * special-case test fixtures (and risk jumping the shared counter
     * to something like 9999 for every real document from now on),
     * just retry with a fresh number on the rare collision.
     */
    private function createWithUniqueNumber(\Closure $attempt, int $maxAttempts = 5): mixed
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                return $attempt();
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if ($i === $maxAttempts - 1) {
                    throw $e;
                }
            }
        }
    }

    private function randomFloat(float $min, float $max): float
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }

    private function roundForUom(InventoryItem $item, float $value): string
    {
        $decimals = $item->uom?->decimal_places ?? 0;
        $rounded = $decimals > 0 ? round($value, $decimals) : (float) max(1, round($value));

        return number_format($rounded, 3, '.', '');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{item: InventoryItem, quantity: string}>  $lines
     */
    private function postGoodsReceipt(
        $lines,
        InventoryLocation $location,
        InventorySupplier $supplier,
        User $performer,
        InventorySequenceService $sequences,
        StockMovementService $movements,
    ): void {
        $this->createWithUniqueNumber(fn () => DB::transaction(function () use ($lines, $location, $supplier, $performer, $sequences, $movements) {
            $grn = InventoryGoodsReceipt::create([
                'grn_no' => $sequences->next('GRN'),
                'receipt_date' => now()->subDays(random_int(1, 20)),
                'location_id' => $location->id,
                'supplier_id' => $supplier->id,
                'reference_type' => 'INVOICE',
                'invoice_no' => 'INV-'.random_int(10000, 99999),
                'invoice_date' => now()->subDays(random_int(1, 20)),
                'status' => InventoryGoodsReceiptStatus::Draft,
                'created_by' => $performer->id,
            ]);

            foreach ($lines->values() as $i => $line) {
                $grn->lines()->create([
                    'line_no' => $i + 1,
                    'item_id' => $line['item']->id,
                    'quantity' => $line['quantity'],
                ]);
            }

            foreach ($grn->lines as $line) {
                $movements->record(
                    item: $line->item,
                    location: $location,
                    type: InventoryMovementType::Receipt,
                    quantity: (string) $line->quantity,
                    performer: $performer,
                    sourceType: InventorySourceType::Grn,
                    sourceId: $grn->id,
                    sourceLineId: $line->id,
                    sourceNo: $grn->grn_no,
                    reference: $grn->invoice_no,
                );
            }

            $grn->update([
                'status' => InventoryGoodsReceiptStatus::Posted,
                'posted_by' => $performer->id,
                'posted_at' => now(),
            ]);
        }));
    }

    /**
     * Mirrors submitAction -> processApproval -> IssueGoods::submitIssue
     * end to end. Returns [itemIndex => InventoryIssueRequestLine] for
     * items that ended up fully issued, so a later Return can link back
     * to a real source line.
     *
     * @param  \Illuminate\Support\Collection<int, array{item: InventoryItem, index: int, quantity: string}>  $lines
     * @return array<int, \App\Models\InventoryIssueRequestLine>
     */
    private function createSubmitApproveIssue(
        $lines,
        InventoryLocation $location,
        User $requester,
        InventoryRecipient $recipient,
        User $approver,
        User $stockAdmin,
        InventorySequenceService $sequences,
        StockMovementService $movements,
    ): array {
        $priority = collect(InventoryPriority::cases())->random();

        $request = $this->createWithUniqueNumber(fn () => InventoryIssueRequest::create([
            'request_no' => $sequences->next('IR'),
            'request_date' => now()->subDays(random_int(0, 15)),
            'requested_by' => $requester->id,
            'location_id' => $location->id,
            'recipient_id' => $recipient->id,
            'purpose' => 'Sample data — office supplies',
            'priority' => $priority,
            'status' => InventoryIssueRequestStatus::Draft,
        ]));

        foreach ($lines->values() as $i => $line) {
            $request->lines()->create([
                'line_no' => $i + 1,
                'item_id' => $line['item']->id,
                'requested_qty' => $line['quantity'],
            ]);
        }

        // Submit
        $request->update([
            'status' => InventoryIssueRequestStatus::Submitted,
            'submitted_at' => now(),
            'total_lines' => $request->lines()->count(),
            'total_qty' => $request->lines()->sum('requested_qty'),
        ]);
        $request->approvalActions()->create(['action' => 'SUBMITTED', 'action_by' => $requester->id, 'action_at' => now()]);

        // Approve (full quantity on every line)
        DB::transaction(function () use ($request, $approver, $movements) {
            foreach ($request->lines as $line) {
                $movements->reserve($line->item, $request->location, (string) $line->requested_qty);
                $line->update(['approved_qty' => $line->requested_qty, 'line_status' => InventoryIssueRequestLineStatus::Approved]);
            }

            $request->update(['status' => InventoryIssueRequestStatus::Approved, 'approver_id' => $approver->id, 'approved_at' => now()]);
            $request->approvalActions()->create(['action' => 'APPROVED', 'action_by' => $approver->id, 'action_at' => now()]);
        });

        // Issue (full quantity on every line)
        $issuedLines = [];

        DB::transaction(function () use ($request, $stockAdmin, $movements, $lines, &$issuedLines) {
            $indexByItemId = $lines->pluck('index', 'item.id');

            foreach ($request->lines as $line) {
                $movements->release($line->item, $request->location, (string) $line->approved_qty);

                $movements->record(
                    item: $line->item,
                    location: $request->location,
                    type: InventoryMovementType::Issue,
                    quantity: (string) $line->approved_qty,
                    performer: $stockAdmin,
                    sourceType: InventorySourceType::Issue,
                    sourceId: $request->id,
                    sourceLineId: $line->id,
                    sourceNo: $request->request_no,
                );

                $line->update(['issued_qty' => $line->approved_qty, 'line_status' => InventoryIssueRequestLineStatus::Issued]);

                if ($indexByItemId->has($line->item_id)) {
                    $issuedLines[$indexByItemId->get($line->item_id)] = $line;
                }
            }

            $request->update([
                'status' => InventoryIssueRequestStatus::Issued,
                'issued_by' => $stockAdmin->id,
                'issued_at' => now(),
                'received_by_name' => $request->requester->name,
            ]);
            $request->approvalActions()->create(['action' => 'ISSUED', 'action_by' => $stockAdmin->id, 'action_at' => now()]);
        });

        return $issuedLines;
    }

    private function postStockTakeAdjustment(
        InventoryItem $item,
        InventoryLocation $location,
        User $performer,
        InventorySequenceService $sequences,
        StockMovementService $movements,
    ): void {
        $this->createWithUniqueNumber(fn () => DB::transaction(function () use ($item, $location, $performer, $sequences, $movements) {
            $stock = $item->stock()->where('location_id', $location->id)->first();
            $systemQty = (string) ($stock->on_hand ?? '0');

            $magnitude = ($item->uom->decimal_places ?? 0) > 0 ? '0.5' : '1.000';
            $sign = mt_rand(0, 1) ? '1' : '-1';
            $delta = bcmul($sign, $magnitude, 3);
            $countedQty = bcadd($systemQty, $delta, 3);
            $countedQty = bccomp($countedQty, '0', 3) < 0 ? '0.000' : $countedQty;
            $difference = bcsub($countedQty, $systemQty, 3);

            if (bccomp($difference, '0', 3) === 0) {
                return;
            }

            $adjustment = InventoryStockAdjustment::create([
                'adjustment_no' => $sequences->next('ADJ'),
                'adjustment_date' => now()->subDays(random_int(0, 10)),
                'location_id' => $location->id,
                'adjustment_type' => InventoryAdjustmentType::StockTake,
                'reason' => 'Sample data — routine stock take',
                'status' => InventoryAdjustmentStatus::Draft,
                'created_by' => $performer->id,
            ]);

            $adjustment->lines()->create([
                'line_no' => 1,
                'item_id' => $item->id,
                'system_qty' => $systemQty,
                'counted_qty' => $countedQty,
                'difference_qty' => $difference,
            ]);

            $type = bccomp($difference, '0', 3) > 0 ? InventoryMovementType::AdjustIn : InventoryMovementType::AdjustOut;

            $movements->record(
                item: $item,
                location: $location,
                type: $type,
                quantity: ltrim($difference, '-'),
                performer: $performer,
                sourceType: InventorySourceType::Adjustment,
                sourceId: $adjustment->id,
                sourceLineId: $adjustment->lines()->first()->id,
                sourceNo: $adjustment->adjustment_no,
                remarks: $adjustment->reason,
            );

            $adjustment->update([
                'status' => InventoryAdjustmentStatus::Posted,
                'posted_by' => $performer->id,
                'posted_at' => now(),
            ]);
        }));
    }

    private function postReturn(
        \App\Models\InventoryIssueRequestLine $issueLine,
        InventoryLocation $location,
        User $performer,
        InventorySequenceService $sequences,
        StockMovementService $movements,
    ): void {
        $qty = $this->roundForUom($issueLine->item, (float) $issueLine->issued_qty * 0.2);

        if (bccomp($qty, '0', 3) <= 0) {
            return;
        }

        $this->createWithUniqueNumber(fn () => DB::transaction(function () use ($issueLine, $location, $performer, $sequences, $movements, $qty) {
            $return = InventoryStockReturn::create([
                'return_no' => $sequences->next('RET'),
                'return_date' => now()->subDays(random_int(0, 5)),
                'issue_request_id' => $issueLine->request_id,
                'returned_by' => $performer->id,
                'location_id' => $location->id,
                'reason' => 'Sample data — unused portion returned',
                'status' => InventoryReturnStatus::Draft,
            ]);

            $return->lines()->create([
                'line_no' => 1,
                'item_id' => $issueLine->item_id,
                'issue_line_id' => $issueLine->id,
                'quantity' => $qty,
                'condition' => InventoryReturnCondition::Good,
            ]);

            $movements->record(
                item: $issueLine->item,
                location: $location,
                type: InventoryMovementType::Return,
                quantity: $qty,
                performer: $performer,
                sourceType: InventorySourceType::Return,
                sourceId: $return->id,
                sourceLineId: $return->lines()->first()->id,
                sourceNo: $return->return_no,
                remarks: $return->reason,
            );

            $issueLine->increment('returned_qty', $qty);

            $return->update(['status' => InventoryReturnStatus::Posted, 'received_by' => $performer->id, 'posted_at' => now()]);
        }));
    }
}
