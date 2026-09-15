<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asset Labels</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f2f2f2;
            padding: 24px;
        }
        .toolbar { margin-bottom: 16px; }
        .toolbar button {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            background: #0e7a82;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }
        .toolbar span { margin-left: 10px; color: #555; font-size: 13px; }
        /* Avery-style 3-column grid — sized for a typical 63.5mm x
           38.1mm label sheet (e.g. Avery 5160-equivalent), spec
           section 6.7's "Avery-style grid" note. */
        .sheet {
            display: grid;
            grid-template-columns: repeat(3, 63.5mm);
            gap: 3mm;
            justify-content: center;
        }
        .label {
            width: 63.5mm;
            height: 38.1mm;
            background: #fff;
            border: 1px dashed #ccc;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 3mm;
            page-break-inside: avoid;
        }
        .label img { width: 26mm; height: 26mm; flex-shrink: 0; }
        .label .text { min-width: 0; }
        .label .name {
            font-size: 11px;
            font-weight: 600;
            color: #111;
            line-height: 1.2;
            margin: 0 0 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        .label .tag {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 10px;
            color: #555;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .label { border: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print</button>
        <span>{{ $labels->count() }} label{{ $labels->count() === 1 ? '' : 's' }}</span>
    </div>
    <div class="sheet">
        @foreach ($labels as $entry)
            <div class="label">
                <img src="{{ $entry['qrDataUri'] }}" alt="QR code">
                <div class="text">
                    <p class="name">{{ $entry['asset']->name }}</p>
                    <p class="tag">{{ $entry['asset']->asset_tag }}</p>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>
