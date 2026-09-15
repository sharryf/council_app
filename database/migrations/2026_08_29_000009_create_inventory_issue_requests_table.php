<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock OUT — a user requests items, an approver approves (a single
 * approver pool, not per-department routing — no departments table in
 * this build), a stock admin issues. The state machine, reservation
 * logic (spec decision D3) and the approve/issue operations are Phase
 * 5 — this migration only lays down the schema. approval_actions is
 * the audit trail of every transition, kept even with a single
 * approval level (spec section 6.7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_issue_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 30)->unique();
            $table->dateTime('request_date');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('location_id')->constrained('inventory_locations');

            $table->string('purpose');
            $table->date('required_by_date')->nullable();
            $table->string('priority', 10)->default('NORMAL'); // LOW, NORMAL, URGENT
            $table->string('delivery_to', 150)->nullable();

            $table->string('status', 20)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_remarks')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->string('received_by_name', 100)->nullable();
            $table->string('receiver_signature_path')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            $table->unsignedInteger('total_lines')->default(0);
            $table->decimal('total_qty', 14, 3)->default(0); // rough sort key only — see spec 6.6, never sum across UoMs
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['requested_by', 'status']);
            $table->index(['approver_id', 'status']);
        });

        Schema::create('inventory_issue_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('inventory_issue_requests')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->decimal('requested_qty', 14, 3);
            $table->decimal('approved_qty', 14, 3)->nullable(); // approver may reduce
            $table->decimal('issued_qty', 14, 3)->default(0);
            $table->decimal('returned_qty', 14, 3)->default(0);
            $table->string('line_status', 20)->default('PENDING');
            // PENDING, APPROVED, REJECTED, ISSUED, PARTIALLY_ISSUED, CANCELLED
            $table->decimal('available_at_request', 14, 3)->nullable(); // snapshot, useful for disputes
            $table->string('line_remarks')->nullable();

            $table->unique(['request_id', 'line_no']);
        });

        Schema::create('inventory_approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('inventory_issue_requests')->cascadeOnDelete();
            $table->string('action', 20); // SUBMITTED, APPROVED, REJECTED, RETURNED_FOR_EDIT, CANCELLED, ISSUED
            $table->foreignId('action_by')->constrained('users');
            $table->dateTime('action_at');
            $table->text('remarks')->nullable();
            $table->smallInteger('level')->default(1);
            $table->json('snapshot')->nullable(); // line quantities at the moment of the action
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_approval_actions');
        Schema::dropIfExists('inventory_issue_request_lines');
        Schema::dropIfExists('inventory_issue_requests');
    }
};
