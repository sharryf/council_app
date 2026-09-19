<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #6b7280; margin-bottom: 4px; }
        .summary { display: flex; gap: 24px; margin: 12px 0 16px; }
        .summary div { border: 1px solid #d1d5db; border-radius: 4px; padding: 6px 12px; }
        .summary .label { color: #6b7280; font-size: 8px; text-transform: uppercase; letter-spacing: 0.03em; }
        .summary .value { font-size: 13px; font-weight: bold; }
        table.report { width: 100%; border-collapse: collapse; }
        table.report th, table.report td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; font-size: 9px; overflow-wrap: break-word; }
        table.report th { background: #f3f4f6; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: 8px; font-weight: bold; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-gray { background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>
    <h1>Asset Audit Session — {{ $session->name }}</h1>
    <div class="meta">{{ $session->scopeDescription() }} · Started by {{ $session->startedBy?->name }} on {{ $session->started_at?->format('d/m/Y H:i') }}</div>
    @if ($session->closed_at)
        <div class="meta">Closed by {{ $session->closedBy?->name }} on {{ $session->closed_at->format('d/m/Y H:i') }}</div>
    @endif
    <div class="meta">Generated: {{ $generatedAt }}</div>

    @php
        $verified = $items->filter(fn ($item) => $item->verified_at !== null)->count();
        $total = $items->count();
    @endphp

    <div class="summary">
        <div>
            <div class="label">Status</div>
            <div class="value">{{ $session->status->getLabel() }}</div>
        </div>
        <div>
            <div class="label">Verified</div>
            <div class="value">{{ $verified }} / {{ $total }}</div>
        </div>
    </div>

    <table class="report">
        <thead>
            <tr>
                <th>Tag</th>
                <th>Asset</th>
                <th>Expected Room</th>
                <th>Verified At</th>
                <th>Verify Method</th>
                <th>Found Room</th>
                <th>Outcome</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $item->asset?->asset_tag }}</td>
                    <td>{{ $item->asset?->name }}</td>
                    <td>{{ $item->expectedRoom?->path() }}</td>
                    <td>{{ $item->verified_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $item->verify_method?->getLabel() }}</td>
                    <td>{{ $item->foundRoom?->path() }}</td>
                    <td>
                        @if ($item->outcome)
                            <span class="badge badge-{{ $item->outcome->getColor() }}">{{ $item->outcome->getLabel() }}</span>
                        @elseif ($item->verified_at)
                            <span class="badge badge-success">Verified</span>
                        @else
                            <span class="badge badge-gray">Pending</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No items in this audit session.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
