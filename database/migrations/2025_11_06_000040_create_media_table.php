<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция для создания таблицы media и связанной таблицы media_images.
 *
 * Таблица media хранит общие поля для всех типов медиа-файлов.
 * Таблица media_images хранит специфичные метаданные изображений (width, height, exif_json).
 *
 * Уникальность медиа-файлов обеспечивается составным индексом (disk, path),
 * так как один и тот же путь может существовать на разных дисках.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name');
            $table->string('ext', 16)->nullable();
            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64)->nullable()->index();
            $table->string('title')->nullable();
            $table->string('alt')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['disk', 'path'], 'media_disk_path_unique');
            $table->index('mime');
            $table->index('created_at');
            $table->index('deleted_at');
        });

        // Создаем таблицу media_images для метаданных изображений
        Schema::create('media_images', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('media_id')->unique();
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->json('exif_json')->nullable();
            $table->timestamps();

            $table->foreign('media_id')
                ->references('id')
                ->on('media')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_images');
        Schema::dropIfExists('media');
    }
};
