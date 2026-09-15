<?php

namespace App\Services\Inventory;

use App\Models\InventorySetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

/**
 * Inventory's own small Browsershot pipeline — a parallel copy of
 * App\Services\Bureau\BureauPdfRenderer rather than a parameterized
 * shared one, since that renderer is hard-wired to BureauSettings and
 * Bureau's own header/footer partials, and threading that coupling out
 * isn't worth it for what is otherwise a two-line render() method.
 * Plain English/LTR — no Dhivehi font loading needed (compare
 * bureau.pdf.partials.fonts).
 */
class InventoryPdfRenderer
{
    public function render(string $view, array $data, string $relativePath): void
    {
        $html = View::make($view, $data)->render();

        $headerImage = $this->settingImageDataUri('pdf_header_image');
        $footerImage = $this->settingImageDataUri('pdf_footer_image');

        $headerHtml = View::make('inventory.pdf.partials.header', [
            'companyName' => InventorySetting::get('company_name'),
            'headerImage' => $headerImage,
        ])->render();
        $footerHtml = View::make('inventory.pdf.partials.footer', [
            'footerImage' => $footerImage,
        ])->render();

        Storage::disk('local')->makeDirectory(dirname($relativePath));

        // A full-width header/footer image needs more vertical room than
        // the plain text fallback — the margin (which is also the box
        // Chromium renders the header/footer template into) grows only
        // when an image is actually configured, so documents without one
        // keep the original, tighter layout.
        Browsershot::html($html)
            ->noSandbox()
            ->writeOptionsToFile()
            ->format('A4')
            ->showBackground()
            ->showBrowserHeaderAndFooter()
            ->headerHtml($headerHtml)
            ->footerHtml($footerHtml)
            ->margins($headerImage ? 45 : 20, 15, $footerImage ? 28 : 18, 15)
            ->savePdf(Storage::disk('local')->path($relativePath));
    }

    private function settingImageDataUri(string $settingKey): ?string
    {
        $path = InventorySetting::get($settingKey);

        if (blank($path)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($path);

        return $bytes ? 'data:image/png;base64,'.base64_encode($bytes) : null;
    }
}
