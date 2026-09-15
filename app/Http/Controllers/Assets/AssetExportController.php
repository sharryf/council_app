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
 * table filters to mirror.
 */
class AssetExportController extends Controller
{
    private function authorize(): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        abort_unless($user && $user->assetRoleList() !== [], 403);
    }

    public function assets(Request $request): StreamedResponse
    {
        $this->authorize();

        $filters = $request->query('filters', []);

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

        return $this->streamCsv('Assets', $header, $rows, 'assets');
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

        $header = ['Tag', 'Asset', 'Expected Room', 'Verified At', 'Verify Method', 'Found Room', 'Outcome', 'Review Action', 'Review Note'];

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
                $item->review_action?->getLabel(),
                $item->review_note,
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
