<?php

namespace App\Services\Bureau;

use App\Models\BureauSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

/**
 * Shared Browsershot pipeline for every Bureau PDF (meeting agenda,
 * meeting minutes, approval-packet request/agenda proposal) — renders
 * the given Blade view as the page body and wraps every page with the
 * council's common header/footer (see BureauSettings::pdf_header_text /
 * pdf_footer_text, editable at Bureau > Meeting Settings), so a
 * letterhead/footer change happens in one place instead of per-document.
 * Header/footer are rendered via Puppeteer's own per-page templates
 * (Browsershot's headerHtml()/footerHtml()), not baked into the body
 * HTML, so they repeat correctly across multi-page documents.
 */
class BureauPdfRenderer
{
    public function render(string $view, array $data, string $relativePath): void
    {
        $settings = BureauSettings::current();

        $html = View::make($view, $data)->render();
        $headerHtml = View::make('bureau.pdf.partials.header', ['settings' => $settings])->render();
        $footerHtml = View::make('bureau.pdf.partials.footer', ['settings' => $settings])->render();

        Storage::disk('local')->makeDirectory(dirname($relativePath));

        // --no-sandbox is what makes Chrome launchable at all in this
        // dev environment (Windows, no chrome sandbox namespaces
        // configured) — reconsider once deployed to a properly
        // configured non-root Linux host, where dropping it is the
        // more secure default.
        // The header/footer HTML carries base64-embedded fonts (see
        // partials/fonts.blade.php) large enough that passing the whole
        // Browsershot request as a single command-line/env argument
        // blows past Windows' ~32K UTF-16 process environment-block
        // limit — writeOptionsToFile() routes it through a temp file
        // instead, which has no such limit.
        Browsershot::html($html)
            ->noSandbox()
            ->writeOptionsToFile()
            ->format('A4')
            ->showBackground()
            ->showBrowserHeaderAndFooter()
            ->headerHtml($headerHtml)
            ->footerHtml($footerHtml)
            ->margins(28, 15, 22, 15)
            ->savePdf(Storage::disk('local')->path($relativePath));
    }
}
