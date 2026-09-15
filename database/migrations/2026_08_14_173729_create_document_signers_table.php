<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Position in the signing sequence. Always set (from the order
            // signers were added), but only enforced when the parent
            // document's signing_mode is "sequential" — see
            // App\Services\DocumentSigning\DocumentSigningService.
            $table->unsignedInteger('order')->default(1);
            $table->string('role_label')->default('Signer');
            $table->string('status')->default('pending');
            $table->string('signature_type')->nullable();
            $table->text('signature_value')->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'user_id']);
            $table->index(['document_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signers');
    }
};
