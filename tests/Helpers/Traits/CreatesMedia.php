<?php

declare(strict_types=1);

namespace Tests\Helpers\Traits;

use App\Models\Media;
use Illuminate\Http\UploadedFile;

/**
 * Трейт для упрощения создания медиа-файлов в тестах.
 */
trait CreatesMedia
{
    /**
     * Создать медиа-запись в БД.
     *
     * @param array<string, mixed> $attributes Атрибуты для фабрики
     * @return Media
     */
    protected function createMediaFile(array $attributes = []): Media
    {
        return Media::factory()->create($attributes);
    }

    /**
     * Создать загружаемое изображение.
     *
     * @param string $name Имя файла
     * @param int $width Ширина изображения
     * @param int $height Высота изображения
     * @return UploadedFile
     */
    protected function createUploadedImage(
        string $name = 'test.jpg',
        int $width = 800,
        int $height = 600
    ): UploadedFile {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    /**
     * Создать загружаемый PDF файл.
     *
     * @param string $name Имя файла
     * @param int $sizeInKb Размер файла в килобайтах
     * @return UploadedFile
     */
    protected function createUploadedPdf(string $name = 'test.pdf', int $sizeInKb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $sizeInKb, 'application/pdf');
    }

    /**
     * Создать загружаемый видео файл.
     *
     * @param string $name Имя файла
     * @param int $sizeInKb Размер файла в килобайтах
     * @return UploadedFile
     */
    protected function createUploadedVideo(string $name = 'test.mp4', int $sizeInKb = 1024): UploadedFile
    {
        return UploadedFile::fake()->create($name, $sizeInKb, 'video/mp4');
    }
}

