<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voided (see App\Enums\DocumentStatus) is an administrative
 * cancellation of a Pending document — distinct from Rejected, which
 * always belongs to a specific signer's own objection with a reason
 * recorded against their signer row. Mirrors the shape of the existing
 * rejection_reason column, plus who/when since a void isn't tied to any
 * signer row to attribute it to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->text('void_reason')->nullable()->after('rejection_reason');
            $table->timestamp('voided_at')->nullable()->after('void_reason');
            $table->foreignId('voided_by')->nullable()->after('voided_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['void_reason', 'voided_at']);
        });
    }
};
