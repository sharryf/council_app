<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('kind', 20); // 'photo' or 'document'
            $table->string('file_path', 500); // local disk path — see App\Enums\AssetRole doc note on storage
            $table->string('file_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['asset_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_attachments');
    }
};
