<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Создание таблицы path_ref_constraints.
 *
 * Хранит ограничения на допустимые PostType для ref-полей в paths.
 * Это первая таблица в архитектуре constraints, которая будет расширена
 * другими таблицами (например, path_media_constraints) для других типов ограничений.
 */
return new class extends Migration {
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::create('path_ref_constraints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('path_id')->constrained('paths')->cascadeOnDelete();
            $table->foreignId('allowed_post_type_id')->constrained('post_types')->restrictOnDelete();
            $table->timestamps();

            // Уникальный индекс на (path_id, allowed_post_type_id) для предотвращения дубликатов
            $table->unique(['path_id', 'allowed_post_type_id'], 'uq_path_ref_constraints_path_post_type');

            // Индекс на path_id для быстрых запросов по path
            $table->index('path_id', 'idx_path_ref_constraints_path_id');

            // Индекс на allowed_post_type_id для обратных запросов
            $table->index('allowed_post_type_id', 'idx_path_ref_constraints_post_type_id');
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('path_ref_constraints');
    }
};
