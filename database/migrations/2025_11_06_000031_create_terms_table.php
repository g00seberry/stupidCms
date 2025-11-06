<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained('taxonomies')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->json('meta_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("ALTER TABLE `terms` ADD `is_active` TINYINT(1) AS (CASE WHEN `deleted_at` IS NULL THEN 1 ELSE 0 END) STORED");
        Schema::table('terms', function (Blueprint $table) {
            $table->unique(['taxonomy_id','slug','is_active'], 'terms_unique_active_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
