<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->text('description');
            $table->date('maintenance_date');
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('previous_status', 20); // App\Enums\AssetStatus — captured at creation
            $table->string('closing_status', 20)->nullable(); // App\Enums\AssetStatus — set when closed
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->string('approval_status', 20)->default('pending'); // App\Enums\AssetMaintenanceApprovalStatus
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            // "Only one OPEN record per asset" (closed_at IS NULL) —
            // same MySQL partial-index limitation as transfer requests
            // above, enforced at the application layer instead.
            $table->index(['asset_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_records');
    }
};
