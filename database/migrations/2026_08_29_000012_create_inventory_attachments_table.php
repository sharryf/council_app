<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic-by-string attachments for GRNs, issue requests,
 * adjustments and items — stored on the private local disk, served
 * through an authenticated controller, same pattern as
 * BureauAgendaItem's attachment_path/attachment_original_name pair
 * (see app/Http/Controllers/Bureau/AgendaAttachmentController.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 30); // GRN, ISSUE_REQUEST, ADJUSTMENT, ITEM
            $table->unsignedBigInteger('entity_id');
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->unsignedInteger('file_size');
            $table->string('mime_type', 100);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('uploaded_at');

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_attachments');
    }
};
