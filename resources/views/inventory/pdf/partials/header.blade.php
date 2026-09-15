@if (filled($headerImage ?? null))
    <div style="width: 100%; text-align: center; padding: 0 15mm 4px;">
        <img src="{{ $headerImage }}" style="display: block; width: 100%; height: auto; max-height: 40mm;">
    </div>
@elseif (filled($companyName))
    <div style="width: 100%; text-align: center; font-family: sans-serif; font-size: 11px; color: #333; padding: 0 15mm 4px; border-bottom: 1px solid #ccc;">
        {{ $companyName }}
    </div>
@else
    <div></div>
@endif
