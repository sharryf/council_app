{{-- Chromium lays out the footer template's top-level children as flex
     items (space-between), not stacked block content — wrapping
     everything in one root div keeps the image and page-number line
     on their own rows instead of side by side. --}}
<div style="width: 100%;">
    @if (filled($footerImage ?? null))
        <div style="text-align: center; padding: 0 15mm 2px;">
            <img src="{{ $footerImage }}" style="display: block; width: 100%; height: auto; max-height: 20mm;">
        </div>
    @endif
    <div style="text-align: center; font-family: sans-serif; font-size: 9px; color: #555; padding: 4px 15mm 0; border-top: 1px solid #ccc;">
        <span class="pageNumber"></span> / <span class="totalPages"></span>
    </div>
</div>
