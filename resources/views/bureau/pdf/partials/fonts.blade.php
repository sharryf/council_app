@php
    /**
     * Base64-embedded (not linked) so Browsershot's headless Chrome
     * never needs network access to render Dhivehi text — the previous
     * Noto Sans Thaana setup relied on a live Google Fonts fetch at
     * PDF-render time, which this removes entirely. Same two files as
     * the on-screen Bureau panel (see BureauPanelProvider): Faruma for
     * body, Mv Galan Normal for headings.
     */
    $farumaBase64 = base64_encode(file_get_contents(public_path('fonts/bureau/Faruma.otf')));
    $galanBase64 = base64_encode(file_get_contents(public_path('fonts/bureau/MvGalanNormal.otf')));
@endphp
<style>
    @font-face {
        font-family: 'Faruma';
        src: url(data:font/otf;base64,{{ $farumaBase64 }}) format('opentype');
        font-weight: 400;
    }

    @font-face {
        font-family: 'Mv Galan Normal';
        src: url(data:font/otf;base64,{{ $galanBase64 }}) format('opentype');
        font-weight: 400;
    }
</style>
