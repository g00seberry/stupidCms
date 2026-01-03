<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Создание таблиц для constraints Path.
 *
 * Создаёт таблицы для хранения ограничений на поля разных типов данных:
 * - path_ref_constraints: ограничения на допустимые PostType для ref-полей
 * - path_media_constraints: ограничения на допустимые MIME-типы для media-полей
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

        Schema::create('path_media_constraints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('path_id')->constrained('paths')->cascadeOnDelete();
            $table->string('allowed_mime'); // MIME-тип, например 'image/jpeg'
            $table->timestamps();

            // Уникальный индекс на (path_id, allowed_mime) для предотвращения дубликатов
            $table->unique(['path_id', 'allowed_mime'], 'uq_path_media_constraints_path_mime');

            // Индекс на path_id для быстрых запросов по path
            $table->index('path_id', 'idx_path_media_constraints_path_id');

            // Индекс на allowed_mime для обратных запросов
            $table->index('allowed_mime', 'idx_path_media_constraints_mime');
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('path_media_constraints');
        Schema::dropIfExists('path_ref_constraints');
    }
};
