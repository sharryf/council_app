<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One agenda item, proposed by any bureau participant with an optional
 * supporting attachment, reviewed (approved/rejected) by the Council
 * President — see App\Enums\BureauAgendaStatus. Approved items are what
 * a meeting auto-attaches once Phase 3 (Meeting scheduling) exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bureau_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('status')->default('pending'); // App\Enums\BureauAgendaStatus
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bureau_agenda_items');
    }
};
