<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_module_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Matches a key in config/modules.php, e.g. "bureau". Only
            // Editor/Approver rows are ever stored — absence of a row
            // means Viewer, the default for every module (see
            // App\Models\User::roleFor()).
            $table->string('module');
            $table->string('level');
            $table->timestamps();

            $table->unique(['user_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_module_levels');
    }
};
