<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Нормализация атрибутов таблицы media.
 *
 * Заменяет уникальность по path на уникальность по (disk, path).
 * Примечание: exif_json теперь хранится в таблице media_images.
 */
return new class extends Migration {
    /**
     * Выполнить миграцию.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique(['path']);
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->unique(['disk', 'path'], 'media_disk_path_unique');
        });
    }

    /**
     * Откатить миграцию.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique('media_disk_path_unique');
            $table->unique('path');
        });
    }
};
