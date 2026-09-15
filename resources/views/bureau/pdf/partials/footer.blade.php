@include('bureau.pdf.partials.fonts')
<div style="width: 100%; direction: rtl; text-align: center; font-family: 'Faruma', sans-serif; font-size: 9px; color: #555; padding: 4px 15mm 0; border-top: 1px solid #ccc;">
    @if (filled($settings->pdf_footer_text))
        <div>{{ $settings->pdf_footer_text }}</div>
    @endif
    <div style="font-family: sans-serif; margin-top: 2px;"><span class="pageNumber"></span> / <span class="totalPages"></span></div>
</div>
