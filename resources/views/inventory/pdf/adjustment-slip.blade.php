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
        table.lines th:nth-child(1), table.lines td:nth-child(1) { width: 14%; }
        table.lines th:nth-child(2), table.lines td:nth-child(2) { width: 40%; }
        table.lines th:nth-child(3), table.lines td:nth-child(3),
        table.lines th:nth-child(4), table.lines td:nth-child(4),
        table.lines th:nth-child(5), table.lines td:nth-child(5) { width: 15.33%; text-align: right; }
        .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
        .signature-block { width: 45%; text-align: center; }
        .signature-block img { max-height: 60px; display: block; margin: 0 auto 4px; }
        .signature-line { border-top: 1px solid #1f2937; margin-top: 40px; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>Adjustment Slip — {{ $adjustment->adjustment_no }}</h1>

    <div class="meta">
        <div>
            <table>
                <tr><td class="label">Type</td><td>{{ $adjustment->adjustment_type->getLabel() }}</td></tr>
                <tr><td class="label">Reason</td><td>{{ $adjustment->reason }}</td></tr>
            </table>
        </div>
        <div>
            <table>
                <tr><td class="label">Adjustment Date</td><td>{{ $adjustment->adjustment_date->format('d/m/Y') }}</td></tr>
                <tr><td class="label">Created By</td><td>{{ $adjustment->createdBy?->name ?? '—' }}</td></tr>
            </table>
        </div>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th>Code</th>
                <th>Item</th>
                <th>System Qty</th>
                <th>Counted Qty</th>
                <th>Difference</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($adjustment->lines as $line)
                <tr>
                    <td>{{ $line->item->code }}</td>
                    <td>{{ $line->item->name }}</td>
                    <td>{{ \Illuminate\Support\Number::format((float) $line->system_qty) }}</td>
                    <td>{{ \Illuminate\Support\Number::format((float) $line->counted_qty) }}</td>
                    <td>{{ \Illuminate\Support\Number::format((float) $line->difference_qty) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signatures">
        <div class="signature-block">
            @if ($creatorSignature)
                <img src="{{ $creatorSignature }}" alt="">
            @endif
            <div class="signature-line">Created By — {{ $adjustment->createdBy?->name }}<br>{{ \App\Models\InventorySetting::localize($adjustment->created_at)->format('d/m/Y H:i') }}</div>
        </div>
        <div class="signature-block">
            @if ($adjustment->approved_at)
                @if ($approvedSignature)
                    <img src="{{ $approvedSignature }}" alt="">
                @endif
                <div class="signature-line">Approved By — {{ $adjustment->approvedBy?->name }}<br>{{ \App\Models\InventorySetting::localize($adjustment->approved_at)->format('d/m/Y H:i') }}</div>
            @endif
        </div>
    </div>
</body>
</html>
