<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Нормализация атрибутов таблицы media.
 *
 * - Преобразует exif_json в JSONB колонку для PostgreSQL
 * - Заменяет уникальность по path на уникальность по (disk, path)
 */
return new class extends Migration {
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropUnique(['path']);
        });

        // Для PostgreSQL: изменяем exif_json на JSONB
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE media ALTER COLUMN exif_json TYPE jsonb USING exif_json::jsonb');
        }

        Schema::table('media', function (Blueprint $table) {
            $table->unique(['disk', 'path'], 'media_disk_path_unique');
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropUnique('media_disk_path_unique');
            $table->unique('path');
        });

        // Для PostgreSQL: возвращаем JSONB обратно в JSON
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE media ALTER COLUMN exif_json TYPE json USING exif_json::json');
        }
    }
};
