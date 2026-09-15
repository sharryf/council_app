<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $asset->name }} — {{ $asset->asset_tag }}</title>
    <style>
        :root {
            --bg: #10161d;
            --surface: #1a222b;
            --surface-2: #212b35;
            --line: #2c3947;
            --ink: #eef2f5;
            --muted: #8fa0af;
            --primary: #0e7a82;
            --accent: #b8934a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            justify-content: center;
            padding: 24px 16px;
            min-height: 100vh;
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
        }
        .photo {
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            background: var(--surface-2);
            display: block;
        }
        .photo-placeholder {
            width: 100%;
            aspect-ratio: 4 / 3;
            background: var(--surface-2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 13px;
        }
        .body { padding: 20px; }
        .tag {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            color: var(--accent);
            letter-spacing: 0.04em;
            margin: 0 0 4px;
        }
        h1 {
            font-size: 22px;
            margin: 0 0 14px;
            line-height: 1.2;
        }
        .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-top: 1px solid var(--line);
        }
        .row .label { color: var(--muted); font-size: 13px; }
        .row .value { font-size: 14px; font-weight: 600; text-align: right; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(14, 122, 130, 0.18);
            color: #5fd4d1;
        }
        .login-link {
            display: block;
            margin-top: 18px;
            padding: 11px;
            text-align: center;
            border-radius: 8px;
            border: 1px solid var(--line);
            color: var(--ink);
            text-decoration: none;
            font-size: 13px;
        }
        .login-link:active { background: var(--surface-2); }
    </style>
</head>
<body>
    <div class="card">
        @if ($photoUrl)
            <img class="photo" src="{{ $photoUrl }}" alt="{{ $asset->name }}">
        @else
            <div class="photo-placeholder">No photo</div>
        @endif
        <div class="body">
            <p class="tag">{{ $asset->asset_tag }}</p>
            <h1>{{ $asset->name }}</h1>

            <div class="row">
                <span class="label">Status</span>
                <span class="value"><span class="badge">{{ $asset->status->getLabel() }}</span></span>
            </div>
            <div class="row">
                <span class="label">Building</span>
                <span class="value">{{ $asset->room->building->name }}</span>
            </div>
            <div class="row">
                <span class="label">Room</span>
                <span class="value">{{ $asset->room->name }}</span>
            </div>

            {{-- Points at the protected page itself, not the login
                 route directly — Filament's Authenticate middleware
                 (built on Laravel's own) stores this as the intended
                 URL and returns here once signed in. --}}
            <a class="login-link" href="{{ route('filament.assets.resources.assets.view', ['record' => $asset]) }}">
                Log in to manage this asset
            </a>
        </div>
    </div>
</body>
</html>
