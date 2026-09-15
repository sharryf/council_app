<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A signer can now be placed at more than one spot on a document (e.g.
 * initials on every page plus a full signature on the last page) — the
 * same one-to-many shape `document_stamps` already has for the
 * organization stamp. Moves placement (page_number/position_x/
 * position_y/box_width/box_height) off `document_signers`, which could
 * only ever hold one, into this child table instead. Existing single
 * placements are carried over before the old columns are dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signer_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_signer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->decimal('position_x', 6, 5);
            $table->decimal('position_y', 6, 5);
            $table->decimal('box_width', 6, 5);
            $table->decimal('box_height', 6, 5);
            $table->timestamps();
        });

        DB::table('document_signers')
            ->whereNotNull('page_number')
            ->orderBy('id')
            ->get()
            ->each(function ($signer): void {
                DB::table('document_signer_placements')->insert([
                    'document_signer_id' => $signer->id,
                    'page_number' => $signer->page_number,
                    'position_x' => $signer->position_x,
                    'position_y' => $signer->position_y,
                    'box_width' => $signer->box_width,
                    'box_height' => $signer->box_height,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn(['page_number', 'position_x', 'position_y', 'box_width', 'box_height']);
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->unsignedInteger('page_number')->nullable()->after('order');
            $table->decimal('position_x', 6, 5)->nullable()->after('page_number');
            $table->decimal('position_y', 6, 5)->nullable()->after('position_x');
            $table->decimal('box_width', 6, 5)->nullable()->after('position_y');
            $table->decimal('box_height', 6, 5)->nullable()->after('box_width');
        });

        DB::table('document_signer_placements')
            ->orderBy('id')
            ->get()
            ->groupBy('document_signer_id')
            ->each(function ($placements, $signerId): void {
                $first = $placements->first();
                DB::table('document_signers')->where('id', $signerId)->update([
                    'page_number' => $first->page_number,
                    'position_x' => $first->position_x,
                    'position_y' => $first->position_y,
                    'box_width' => $first->box_width,
                    'box_height' => $first->box_height,
                ]);
            });

        Schema::dropIfExists('document_signer_placements');
    }
};
