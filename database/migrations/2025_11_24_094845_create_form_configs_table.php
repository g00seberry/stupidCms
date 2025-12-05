<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_type_id')
                ->constrained('post_types')
                ->restrictOnDelete();
            $table->foreignId('blueprint_id')
                ->constrained('blueprints')
                ->cascadeOnDelete();
            $table->json('config_json')->nullable(false);
            $table->timestamps();

            // Составной уникальный ключ
            $table->unique(['post_type_id', 'blueprint_id'], 'uq_form_configs_post_type_blueprint');

            // Индексы для быстрого поиска
            $table->index('post_type_id');
            $table->index('blueprint_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_configs');
    }
};
