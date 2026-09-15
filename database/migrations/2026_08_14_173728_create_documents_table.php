<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('file_path');
            $table->string('file_original_name');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('signing_mode')->default('sequential');
            $table->string('status')->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->string('signed_file_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
