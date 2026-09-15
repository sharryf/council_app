@include('bureau.pdf.partials.fonts')
@if (filled($settings->pdf_header_text))
    <div style="width: 100%; direction: rtl; text-align: center; font-family: 'Mv Galan Normal', 'Faruma', sans-serif; font-size: 11px; color: #333; padding: 0 15mm 4px; border-bottom: 1px solid #ccc;">
        {{ $settings->pdf_header_text }}
    </div>
@else
    <div></div>
@endif
