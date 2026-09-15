<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a signer's signature lands on the actual document page, set by
 * dragging their box into place in the new document wizard (see
 * App\Filament\Resources\DocumentSigning\Documents\Pages\CreateDocument).
 * Coordinates are fractions of the page (0–1), not pixels or PDF
 * points, so they're independent of both the browser's render size and
 * the PDF's native page size — see DocumentStampService for the
 * conversion to actual PDF units when stamping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->unsignedInteger('page_number')->nullable()->after('order');
            $table->decimal('position_x', 6, 5)->nullable()->after('page_number');
            $table->decimal('position_y', 6, 5)->nullable()->after('position_x');
            $table->decimal('box_width', 6, 5)->nullable()->after('position_y');
            $table->decimal('box_height', 6, 5)->nullable()->after('box_width');
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn(['page_number', 'position_x', 'position_y', 'box_width', 'box_height']);
        });
    }
};
