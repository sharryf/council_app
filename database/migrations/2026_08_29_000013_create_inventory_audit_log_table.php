<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 30);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 30); // CREATE, UPDATE, DELETE, POST, REVERSE, APPROVE, REJECT, ROLE_ASSIGN
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->index(['entity_type', 'entity_id', 'changed_at']);
            $table->index(['changed_by', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_audit_log');
    }
};
