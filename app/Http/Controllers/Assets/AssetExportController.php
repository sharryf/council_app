<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAuditItem;
use App\Models\AssetAuditSession;
use App\Models\AssetMaintenanceRecord;
use App\Models\AssetTransferRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports (spec section 8/Phase 7) — plain CSV rather than a real
 * .xlsx writer, same precedent as
 * App\Http\Controllers\Inventory\ReportExportController (this app has
 * no Excel-writing library installed, and CSV opens natively in Excel
 * anyway). assets() mirrors whatever filters are active on the Assets
 * list's own table (passed as a `filters` query param, shaped exactly
 * like Livewire's own $tableFilters — see ListAssets' export action)
 * so the export matches what's on screen; the other three have no
 * table filters to mirror. assetsPdf() shares the same filtered
 * dataset through assetsReportData() and renders it via a bare
 * Browsershot call (no InventoryPdfRenderer-style settings-driven
 * header/footer — Assets has no equivalent AssetSetting keys for
 * that), matching ReportExportController::pdf()'s CSV/PDF pairing. Its
 * column set is independently user-chosen (see EXPORT_FIELDS below),
 * unlike the CSV export's fixed register-format columns.
 */
class AssetExportController extends Controller
{
    /**
     * The full universe of columns the PDF export's field-picker
     * checkbox list can show, keyed for both the request's `fields[]`
     * query values and exportFieldValue()'s match arm — order here is
     * the canonical order for both the checkbox list and the rendered
     * PDF, regardless of the order fields were ticked in.
     */
    public const EXPORT_FIELDS = [
        'asset_tag' => 'Asset Tag',
        'name' => 'Asset Name',
        'category' => 'Category',
        'status' => 'Condition',
        'brand' => 'Brand',
        'model' => 'Model',
        'serial_number' => 'Serial No',
        'purchase_date' => 'Purchase Date',
        'purchase_price' => 'Purchase Price',
        'main_inventory_no' => 'Main Inventory No',
        'asset_class_code' => 'Code',
        'location' => 'Location',
        'room' => 'Room',
        'lifecycle_status' => 'Register Status',
        'description' => 'Description',
        'vendor' => 'Vendor',
        'fund_code' => 'Fund Code',
        'gl_code' => 'GL Code',
        'po_number' => 'PO Number',
        'voucher_number' => 'Voucher Number',
        'donation_reference_no' => 'Donation Ref No',
        'asset_type' => 'Asset Type',
    ];

    /**
     * Ticked by default in the field-picker, in this exact order —
     * everything else in EXPORT_FIELDS starts unticked.
     */
    public const DEFAULT_EXPORT_FIELDS = [
        'asset_tag', 'name', 'category', 'status', 'brand', 'model',
        'serial_number', 'purchase_date', 'purchase_price',
    ];

    private function authorize(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        abort_unless($user && $user->assetRoleList() !== [], 403);
    }

    public function assets(Request $request): StreamedResponse
    {
        $this->authorize();

        [$header, $rows] = $this->assetsReportData($request->query('filters', []));

        return $this->streamCsv('Assets', $header, $rows, 'assets');
    }

    public function assetsPdf(Request $request): StreamedResponse
    {
        $this->authorize();

        // Re-key against EXPORT_FIELDS (not the raw query values) so
        // the result is both validated and always in canonical order,
        // no matter what order the field-picker's checkboxes submitted.
        $requested = $request->query('fields', self::DEFAULT_EXPORT_FIELDS);
        $fields = array_keys(array_intersect_key(self::EXPORT_FIELDS, array_flip($requested)));

        if ($fields === []) {
            $fields = self::DEFAULT_EXPORT_FIELDS;
        }

        $query = $this->filteredAssetsQuery($request->query('filters', []));

        $header = array_map(fn (string $field): string => self::EXPORT_FIELDS[$field], $fields);

        $rows = $query->orderBy('name')->get()->map(fn (Asset $asset): array => array_map(
            fn (string $field) => $this->exportFieldValue($asset, $field),
            $fields,
        ));

        $html = view('assets.pdf.assets-report', [
            'header' => $header,
            'rows' => $rows,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->render();

        $relativePath = 'assets/exports/assets-'.now()->timestamp.'.pdf';
        Storage::disk('local')->makeDirectory(dirname($relativePath));

        Browsershot::html($html)
            ->noSandbox()
            ->writeOptionsToFile()
            ->format('A4')
            ->landscape()
            ->showBackground()
            ->margins(15, 10, 15, 10)
            ->savePdf(Storage::disk('local')->path($relativePath));

        return Storage::disk('local')->response($relativePath, 'assets-'.now()->format('Y-m-d').'.pdf');
    }

    private function exportFieldValue(Asset $asset, string $field): mixed
    {
        return match ($field) {
            'asset_tag' => $asset->asset_tag,
            'name' => $asset->name,
            'category' => $asset->category?->parent?->name ?? $asset->category?->name,
            'status' => $asset->status?->getLabel(),
            'brand' => $asset->brand,
            'model' => $asset->model,
            'serial_number' => $asset->serial_number,
            'purchase_date' => $asset->purchase_date?->toDateString(),
            'purchase_price' => $asset->purchase_price,
            'main_inventory_no' => $asset->main_inventory_no,
            'asset_class_code' => $asset->category?->asset_class_code,
            'location' => $asset->room?->building?->name,
            'room' => $asset->room?->name,
            'lifecycle_status' => $asset->lifecycle_status?->getLabel(),
            'description' => $asset->description,
            'vendor' => $asset->vendor,
            'fund_code' => $asset->fund_code,
            'gl_code' => $asset->category?->gl_code ?? $asset->category?->parent?->gl_code,
            'po_number' => $asset->po_number,
            'voucher_number' => $asset->voucher_number,
            'donation_reference_no' => $asset->donation_reference_no,
            'asset_type' => $asset->asset_type?->getLabel(),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredAssetsQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = Asset::query()->with(['category.parent', 'room.building']);

        if ($categoryIds = $filters['category_id']['values'] ?? null) {
            $query->whereIn('category_id', $categoryIds);
        }

        if ($statuses = $filters['status']['values'] ?? null) {
            $query->whereIn('status', $statuses);
        }

        if ($buildingId = $filters['room.building_id']['value'] ?? null) {
            $query->whereHas('room', fn ($q) => $q->where('building_id', $buildingId));
        }

        if ($from = $filters['purchase_date']['purchased_from'] ?? null) {
            $query->whereDate('purchase_date', '>=', $from);
        }

        if ($to = $filters['purchase_date']['purchased_to'] ?? null) {
            $query->whereDate('purchase_date', '<=', $to);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: array<int, string>, 1: \Illuminate\Support\Collection<int, array<int, mixed>>}
     */
    private function assetsReportData(array $filters): array
    {
        $query = $this->filteredAssetsQuery($filters);

        // Column set and order mirror the council's own current asset
        // register ("Asset Detailed Report.xlsx"), minus the columns
        // this app doesn't track (ActivityDetail, SAPAssetNo, POVoid —
        // removed on request), plus DonationReferenceNo for donated
        // assets, which the source register had no equivalent for.
        $header = [
            'MainInventoryNo', 'ItemInventoryNo', 'ItemName', 'Asset Location', 'Sub Location',
            'rcvDate', 'AssetValue', 'FundCode', 'GLCode', 'AssetClass',
            'PONumber', 'ExtraDetail', 'VoucherFull', 'AssetType', 'DonationReferenceNo',
        ];

        $rows = $query->orderBy('name')->get()->map(fn (Asset $asset): array => [
            $asset->main_inventory_no,
            $asset->asset_tag,
            $asset->name,
            $asset->room?->building?->name,
            $asset->room?->name,
            $asset->purchase_date?->toDateString(),
            $asset->purchase_price,
            $asset->fund_code,
            $asset->category?->gl_code ?? $asset->category?->parent?->gl_code,
            $asset->category?->asset_class_code,
            $asset->po_number,
            $asset->description,
            $asset->voucher_number,
            $asset->asset_type?->getLabel(),
            $asset->donation_reference_no,
        ]);

        return [$header, $rows];
    }

    public function transfers(): StreamedResponse
    {
        $this->authorize();

        $header = ['Tag', 'Asset', 'From', 'To', 'Requested By', 'Requested At', 'Status', 'Decided By', 'Decided At', 'Note'];

        $rows = AssetTransferRequest::query()
            ->with(['asset', 'fromRoom.building', 'toRoom.building', 'requestedBy', 'decidedBy'])
            ->orderByDesc('requested_at')
            ->get()
            ->map(fn (AssetTransferRequest $r): array => [
                $r->asset?->asset_tag,
                $r->asset?->name,
                $r->fromRoom?->path(),
                $r->toRoom?->path(),
                $r->requestedBy?->name,
                $r->requested_at?->toDateTimeString(),
                $r->status->getLabel(),
                $r->decidedBy?->name,
                $r->decided_at?->toDateTimeString(),
                $r->decision_note,
            ]);

        return $this->streamCsv('Transfers', $header, $rows, 'transfers');
    }

    public function maintenance(): StreamedResponse
    {
        $this->authorize();

        $header = ['Tag', 'Asset', 'Description', 'Date', 'Cost', 'Logged By', 'Approval', 'Closing Status', 'Closed At'];

        $rows = AssetMaintenanceRecord::query()
            ->with(['asset', 'recordedBy'])
            ->orderByDesc('maintenance_date')
            ->get()
            ->map(fn (AssetMaintenanceRecord $r): array => [
                $r->asset?->asset_tag,
                $r->asset?->name,
                $r->description,
                $r->maintenance_date?->toDateString(),
                $r->cost,
                $r->recordedBy?->name,
                $r->approval_status->getLabel(),
                $r->closing_status?->getLabel(),
                $r->closed_at?->toDateTimeString(),
            ]);

        return $this->streamCsv('Maintenance', $header, $rows, 'maintenance');
    }

    public function auditItems(AssetAuditSession $session): StreamedResponse
    {
        $this->authorize();

        $header = ['Tag', 'Asset', 'Expected Room', 'Verified At', 'Verify Method', 'Found Room', 'Outcome'];

        $rows = AssetAuditItem::query()
            ->where('session_id', $session->id)
            ->with(['asset', 'expectedRoom.building', 'foundRoom.building'])
            ->get()
            ->map(fn (AssetAuditItem $item): array => [
                $item->asset?->asset_tag,
                $item->asset?->name,
                $item->expectedRoom?->path(),
                $item->verified_at?->toDateTimeString(),
                $item->verify_method?->getLabel(),
                $item->foundRoom?->path(),
                $item->outcome?->getLabel(),
            ]);

        return $this->streamCsv("Audit — {$session->name}", $header, $rows, "audit-{$session->id}");
    }

    /**
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    private function streamCsv(string $label, array $header, iterable $rows, string $filenameSlug): StreamedResponse
    {
        return response()->streamDownload(function () use ($label, $header, $rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ["Report: {$label}", 'Generated: '.now()->toDateTimeString()]);
            fputcsv($out, []);
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "assets-{$filenameSlug}-".now()->format('Y-m-d').'.csv');
    }
}
