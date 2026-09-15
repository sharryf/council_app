<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 16px; }
        .meta div { width: 48%; }
        .meta table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; }
        .meta td.label { color: #6b7280; width: 40%; }
        table.lines { width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 12px; }
        table.lines th, table.lines td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; font-size: 11px; overflow-wrap: break-word; }
        table.lines th { background: #f3f4f6; }
        table.lines th:nth-child(1), table.lines td:nth-child(1) { width: 12%; }
        table.lines th:nth-child(2), table.lines td:nth-child(2) { width: 36%; }
        table.lines th:nth-child(3), table.lines td:nth-child(3) { width: 10%; }
        table.lines th:nth-child(4), table.lines td:nth-child(4),
        table.lines th:nth-child(5), table.lines td:nth-child(5),
        table.lines th:nth-child(6), table.lines td:nth-child(6) { width: 14%; text-align: right; }
        .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
        .signature-block { width: 45%; text-align: center; }
        .signature-block img { max-height: 60px; display: block; margin: 0 auto 4px; }
        .signature-line { border-top: 1px solid #1f2937; margin-top: 40px; padding-top: 4px; }
        .receipt { border: 1px solid #d1d5db; border-radius: 4px; padding: 10px 12px; margin-top: 12px; }
        .receipt table.receipt-items { width: 100%; border-collapse: collapse; font-size: 11px; }
        .receipt table.receipt-items td { padding: 3px 4px; border-bottom: 1px solid #e5e7eb; }
        .receipt table.receipt-items td.qty { width: 15%; text-align: right; }
        .receipt .signatures { margin-top: 12px; }
        .receipt .signature-block img { max-height: 45px; }
        .receipt .signature-line { margin-top: 24px; }
    </style>
</head>
<body>
    <h1>Issue Slip — {{ $request->request_no }}</h1>

    <div class="meta">
        <div>
            <table>
                <tr><td class="label">Requester</td><td>{{ $request->requester->name }}</td></tr>
                <tr><td class="label">Issuing To</td><td>{{ $request->recipient->name ?? $request->requester->name }}</td></tr>
                <tr><td class="label">Purpose</td><td>{{ $request->purpose }}</td></tr>
                <tr><td class="label">Location</td><td>{{ $request->location->name }}</td></tr>
            </table>
        </div>
        <div>
            <table>
                <tr><td class="label">Request Date</td><td>{{ \App\Models\InventorySetting::localize($request->request_date)->format('d/m/Y') }}</td></tr>
                <tr><td class="label">Issued Date</td><td>{{ \App\Models\InventorySetting::localize($request->issued_at)?->format('d/m/Y') ?? '—' }}</td></tr>
            </table>
        </div>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th>Code</th>
                <th>Item</th>
                <th>UoM</th>
                <th>Requested Qty</th>
                <th>Approved Qty</th>
                <th>Issued Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($request->lines as $line)
                @if ((float) $line->issued_qty > 0)
                    <tr>
                        <td>{{ $line->item->code }}</td>
                        <td>{{ $line->item->name }}</td>
                        <td>{{ $line->item->uom?->code }}</td>
                        <td>{{ \Illuminate\Support\Number::format((float) $line->requested_qty) }}</td>
                        <td>{{ $line->approved_qty !== null ? \Illuminate\Support\Number::format((float) $line->approved_qty) : '—' }}</td>
                        <td>{{ \Illuminate\Support\Number::format((float) $line->issued_qty) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    @if ($request->approved_at)
        <div class="signatures" style="margin-top: 24px;">
            <div class="signature-block">
                @if ($approverSignature)
                    <img src="{{ $approverSignature }}" alt="">
                @endif
                <div class="signature-line">Approved By — {{ $request->approver?->name }}<br>{{ \App\Models\InventorySetting::localize($request->approved_at)->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    @endif

    @if ($request->receipts->isNotEmpty())
        {{-- One card per issue batch — each batch's own issuer AND
             receiver signatures, and exactly the items involved, never
             a total or a signature misattributed to whoever was last. --}}
        @foreach ($request->receipts as $receipt)
            <div class="receipt">
                <table class="receipt-items">
                    <tbody>
                        @foreach ($receipt->movements as $movement)
                            <tr>
                                <td>{{ $movement->item->code }}</td>
                                <td>{{ $movement->item->name }}</td>
                                <td class="qty">{{ \Illuminate\Support\Number::format((float) $movement->quantity) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="signatures">
                    <div class="signature-block">
                        @if ($receiptIssuerSignatures[$receipt->id] ?? null)
                            <img src="{{ $receiptIssuerSignatures[$receipt->id] }}" alt="">
                        @endif
                        <div class="signature-line">Issued By — {{ $receipt->issuedByUser?->name }}<br>{{ \App\Models\InventorySetting::localize($receipt->issued_at)->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="signature-block">
                        @if ($receiptSignatures[$receipt->id] ?? null)
                            <img src="{{ $receiptSignatures[$receipt->id] }}" alt="">
                        @endif
                        <div class="signature-line">Received By — {{ $receipt->received_by_name }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    @else
        {{-- Legacy fallback: requests issued before per-batch receipts
             existed have no rows here — show the single summary this
             document always showed. --}}
        <div class="signatures">
            <div class="signature-block">
                @if ($issuerSignature)
                    <img src="{{ $issuerSignature }}" alt="">
                @endif
                <div class="signature-line">Issued By — {{ $request->issuedByUser?->name }}</div>
            </div>
            <div class="signature-block">
                @if ($receiverSignature)
                    <img src="{{ $receiverSignature }}" alt="">
                @endif
                <div class="signature-line">Received By — {{ $request->received_by_name ?? '' }}</div>
            </div>
        </div>
    @endif
</body>
</html>
