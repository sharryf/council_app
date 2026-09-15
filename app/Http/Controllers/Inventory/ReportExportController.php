<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventorySourceType;
use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Http\Controllers\Controller;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryIssueRequest;
use App\Models\InventoryItem;
use App\Models\InventorySetting;
use App\Models\InventoryStockMovement;
use App\Services\Inventory\InventoryPdfRenderer;
use App\Services\Inventory\ReorderCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Spec 14's CSV export for each of the 4 reports this phase built
 * (see InventoryReports) — every export's first rows state the report
 * name and a generation timestamp, per spec's own requirement. Mirrors
 * whatever filters are active on the report's own table (passed as a
 * `filters` query param, shaped exactly like Livewire's own
 * $tableFilters) so the exported dataset matches what's on screen —
 * the Reorder report has no filters at all, so it's unaffected.
 */
class ReportExportController extends Controller
{
    use HasInventoryRoleAccess;

    public function show(Request $request, string $report, ReorderCalculationService $reorderCalculation): StreamedResponse
    {
        abort_unless(self::userIsStockAdminOrAbove(), 403);

        [$label, $header, $rows] = $this->resolveReport($report, $request->query('filters', []), $reorderCalculation);

        return response()->streamDownload(function () use ($label, $header, $rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ["Report: {$label}", 'Generated: '.InventorySetting::localize(now())->toDateTimeString()]);
            fputcsv($out, []);
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "inventory-{$report}-".now()->format('Y-m-d').'.csv');
    }

    /**
     * Same 4 datasets as show()'s CSV — a plain HTML table rendered
     * through the same InventoryPdfRenderer/Browsershot pipeline as the
     * Issue Slip and Adjustment Slip, rather than a second CSV-only
     * report format.
     */
    public function pdf(Request $request, string $report, ReorderCalculationService $reorderCalculation, InventoryPdfRenderer $renderer): StreamedResponse
    {
        abort_unless(self::userIsStockAdminOrAbove(), 403);

        [$label, $header, $rows] = $this->resolveReport($report, $request->query('filters', []), $reorderCalculation);

        $relativePath = "inventory/reports/{$report}-".now()->timestamp.'.pdf';

        $renderer->render('inventory.pdf.report', [
            'label' => $label,
            'header' => $header,
            'rows' => $rows,
            'generatedAt' => InventorySetting::localize(now())->format('d/m/Y H:i'),
        ], $relativePath);

        return Storage::disk('local')->response($relativePath, "inventory-{$report}-".now()->format('Y-m-d').'.pdf');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveReport(string $report, array $filters, ReorderCalculationService $reorderCalculation): array
    {
        return match ($report) {
            'stock-movement' => $this->stockMovement($filters),
            'reorder' => $this->reorder($reorderCalculation),
            'issue-summary' => $this->issueSummary($filters),
            default => $this->stockBalance($reorderCalculation, $filters),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function stockBalance(ReorderCalculationService $service, array $filters): array
    {
        $query = InventoryItem::query()->with(['uom', 'stock'])->orderBy('code');

        if (filled($categoryId = $filters['category_id']['value'] ?? null)) {
            $query->where('category_id', $categoryId);
        }

        // Filter::make('hide_zero_stock') defaults to active (matching
        // the on-screen table's own default) unless explicitly turned
        // off.
        if ($filters['hide_zero_stock']['isActive'] ?? true) {
            $query->whereHas('stock', fn ($q) => $q->where('on_hand', '>', 0));
        }

        $rows = $query->get()
            ->map(fn (InventoryItem $item): array => [
                $item->code, $item->name, $item->uom?->code,
                Number::format((float) $item->stock->sum('on_hand')), Number::format((float) $item->stock->sum('reserved')),
                Number::format((float) $service->availableFor($item)), Number::format((float) $item->reorder_level),
            ]);

        return ['Stock Balance', ['Code', 'Name', 'UoM', 'On Hand', 'Reserved', 'Available', 'Reorder Level'], $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function stockMovement(array $filters): array
    {
        $query = InventoryStockMovement::query()->with(['item', 'performer'])->latest('movement_date');

        if (filled($itemId = $filters['item_id']['value'] ?? null)) {
            $query->where('item_id', $itemId);
        }

        if (filled($movementType = $filters['movement_type']['value'] ?? null)) {
            $query->where('movement_type', $movementType);
        }

        if (filled($performedBy = $filters['performed_by']['value'] ?? null)) {
            $query->where('performed_by', $performedBy);
        }

        if (filled($from = $filters['movement_date']['from'] ?? null)) {
            $query->whereDate('movement_date', '>=', $from);
        }

        if (filled($until = $filters['movement_date']['until'] ?? null)) {
            $query->whereDate('movement_date', '<=', $until);
        }

        $issueCache = [];
        $grnCache = [];

        $rows = $query->limit(5000)->get()
            ->map(function (InventoryStockMovement $m) use (&$issueCache, &$grnCache): array {
                $issuedTo = ($m->source_type === InventorySourceType::Issue && filled($m->source_id))
                    ? ($issueCache[$m->source_id] ??= InventoryIssueRequest::with('recipient')->find($m->source_id))?->recipient?->name
                    : null;

                $supplier = ($m->source_type === InventorySourceType::Grn && filled($m->source_id))
                    ? ($grnCache[$m->source_id] ??= InventoryGoodsReceipt::with('supplier')->find($m->source_id))?->supplier?->name
                    : null;

                return [
                    InventorySetting::localize($m->movement_date)->toDateTimeString(), $m->source_no, $m->movement_type->getLabel(),
                    $m->direction > 0 ? Number::format((float) $m->quantity) : '', $m->direction < 0 ? Number::format((float) $m->quantity) : '',
                    Number::format((float) $m->balance_after), $issuedTo, $supplier, $m->reference, $m->performer?->name,
                ];
            });

        return ['Stock Movement / Item Ledger', ['Date', 'Doc No', 'Type', 'In', 'Out', 'Balance', 'Issued To', 'Supplier', 'Reference', 'User'], $rows];
    }

    private function reorder(ReorderCalculationService $service): array
    {
        $rows = $service->candidateItems()->map(function (InventoryItem $item) use ($service): array {
            $available = $service->availableFor($item);
            $daysOfCover = $service->daysOfCover($item, $available);

            return [
                $item->code, $item->name, $item->uom?->code, Number::format((float) $available), Number::format((float) $item->reorder_level),
                Number::format((float) bcsub((string) $item->reorder_level, $available, 3)), Number::format((float) $service->suggestedQty($item, $available)),
                $daysOfCover === null ? '' : (string) (int) round((float) $daysOfCover), $item->defaultSupplier?->name, $item->last_received_date?->toDateString(),
            ];
        });

        return ['Reorder Report', ['Item', 'Name', 'UoM', 'Available', 'Reorder Level', 'Shortfall', 'Suggested Qty', 'Days of Cover', 'Supplier', 'Last Received'], $rows];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function issueSummary(array $filters): array
    {
        $query = InventoryIssueRequest::query()->with(['requester', 'approver'])->withCount('lines')->latest('request_date');

        if (filled($status = $filters['status']['value'] ?? null)) {
            $query->where('status', $status);
        }

        if (filled($from = $filters['request_date']['from'] ?? null)) {
            $query->whereDate('request_date', '>=', $from);
        }

        if (filled($until = $filters['request_date']['until'] ?? null)) {
            $query->whereDate('request_date', '<=', $until);
        }

        $rows = $query->limit(5000)->get()
            ->map(fn (InventoryIssueRequest $r): array => [
                $r->request_no, InventorySetting::localize($r->request_date)->toDateString(), $r->requester?->name, $r->purpose,
                (string) $r->lines_count, $r->status->getLabel(), $r->approver?->name, InventorySetting::localize($r->issued_at)?->toDateString(),
            ]);

        return ['Issue Summary', ['Request No', 'Date', 'Requester', 'Purpose', 'Lines', 'Status', 'Approver', 'Issued Date'], $rows];
    }
}
