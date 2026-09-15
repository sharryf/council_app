<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label — {{ $asset->asset_tag }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f2f2f2;
            display: flex;
            flex-direction: column;
            align-items: center;
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
        .label {
            width: 62mm;
            height: 35mm;
            background: #fff;
            border: 1px solid #ccc;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 3mm;
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
    </div>
    <div class="label">
        <img src="{{ $qrDataUri }}" alt="QR code">
        <div class="text">
            <p class="name">{{ $asset->name }}</p>
            <p class="tag">{{ $asset->asset_tag }}</p>
        </div>
    </div>
</body>
</html>
