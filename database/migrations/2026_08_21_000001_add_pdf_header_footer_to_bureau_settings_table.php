<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text header/footer shown on every page of every Bureau PDF
 * (meeting request, meeting agenda proposal, meeting agenda, meeting
 * minutes) via Puppeteer's native per-page header/footer — see
 * App\Services\Bureau\BureauPdfRenderer and
 * resources/views/bureau/pdf/partials/{header,footer}.blade.php. Kept
 * as plain text (not HTML) so the Module Admin can edit them from the
 * settings page without needing to know markup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureau_settings', function (Blueprint $table) {
            $table->text('pdf_header_text')->nullable()->after('next_private_meeting_number');
            $table->text('pdf_footer_text')->nullable()->after('pdf_header_text');
        });
    }

    public function down(): void
    {
        Schema::table('bureau_settings', function (Blueprint $table) {
            $table->dropColumn(['pdf_header_text', 'pdf_footer_text']);
        });
    }
};
