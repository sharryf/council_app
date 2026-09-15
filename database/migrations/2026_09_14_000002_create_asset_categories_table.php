<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            // NULL = top-level category; NOT NULL = sub-category. Depth
            // is capped at 2 by the application layer (rejecting a
            // child whose parent already has a parent) — MySQL has no
            // CHECK-based way to enforce self-reference depth.
            $table->foreignId('parent_id')->nullable()->constrained('asset_categories')->cascadeOnDelete();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            // Not scoped to "WHERE deleted_at IS NULL" — MySQL has no
            // partial/filtered unique index. Active-row uniqueness is
            // instead enforced by the Filament form's validation rule
            // (see AssetCategoryForm), matching this app's general
            // preference for hand-rolled checks over exotic DB
            // constraints.
            $table->unique(['parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
