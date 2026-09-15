<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Placements of the organization stamp (see App\Models\DocumentSigningOrganization)
 * on one document — a document can have it placed more than once (e.g.
 * once per page that needs it). Same page-relative coordinate scheme as
 * document_signers' placement columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_stamps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->decimal('position_x', 6, 5);
            $table->decimal('position_y', 6, 5);
            $table->decimal('box_width', 6, 5);
            $table->decimal('box_height', 6, 5);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_stamps');
    }
};
