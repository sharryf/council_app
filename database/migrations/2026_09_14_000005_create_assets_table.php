<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 40)->unique(); // human-facing, e.g. AST-000123 — immutable after creation
            $table->string('public_token', 32)->unique(); // unguessable QR public-URL token, generated at creation
            $table->string('name', 200);
            $table->foreignId('category_id')->constrained('asset_categories');
            $table->string('brand', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->text('description')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->string('vendor', 200)->nullable();
            $table->foreignId('room_id')->constrained('asset_rooms');
            $table->string('status', 20); // App\Enums\AssetStatus
            // No FK constraint — asset_attachments.asset_id references
            // this table, so a constraint here would be circular. The
            // photo requirement is enforced at the application layer
            // (see AssetResource's create flow), matching the plan's
            // section 2.4/3.3 guidance.
            $table->unsignedBigInteger('photo_attachment_id')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('purchase_date');
            $table->index('serial_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
