<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #6b7280; margin-bottom: 16px; }
        table.report { width: 100%; border-collapse: collapse; }
        table.report th, table.report td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; font-size: 9px; overflow-wrap: break-word; }
        table.report th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Assets Register</h1>
    <div class="meta">Generated: {{ $generatedAt }}</div>

    <table class="report">
        <thead>
            <tr>
                @foreach ($header as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($header) }}">No data matches the current filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
