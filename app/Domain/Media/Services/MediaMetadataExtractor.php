<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Images\ImageProcessor;
use Illuminate\Http\UploadedFile;

/**
 * Сервис для извлечения метаданных из медиа-файлов.
 *
 * Извлекает размеры изображений, EXIF данные и другую информацию
 * из загруженных файлов.
 *
 * @package App\Domain\Media\Services
 */
class MediaMetadataExtractor
{
    /**
     * @param \App\Domain\Media\Images\ImageProcessor $images Процессор изображений
     */
    public function __construct(
        private readonly ImageProcessor $images
    ) {
    }

    /**
     * Извлечь метаданные из медиа-файла.
     *
     * Для изображений извлекает размеры и EXIF данные (если доступны).
     * Для видео/аудио может извлекать длительность (не реализовано).
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @param string|null $mime MIME-тип файла (если не указан, определяется автоматически)
     * @return array{width: ?int, height: ?int, duration_ms: ?int, exif: ?array} Метаданные файла
     */
    public function extract(UploadedFile $file, ?string $mime = null): array
    {
        $mime ??= $file->getMimeType() ?? $file->getClientMimeType();

        $width = null;
        $height = null;
        $duration = null;
        $exif = null;

        if (is_string($mime) && str_starts_with($mime, 'image/')) {
            // Пытаемся через универсальный процессор (даже если GD не поддерживает формат)
            $bytes = @file_get_contents($file->getRealPath() ?: $file->getPathname() ?: '');
            if (is_string($bytes) && $bytes !== '') {
                try {
                    $img = $this->images->open($bytes);
                    $width = $this->images->width($img);
                    $height = $this->images->height($img);
                    $this->images->destroy($img);
                } catch (\Throwable) {
                    // Fallback на getimagesize, если драйвер не смог открыть
                    $imageInfo = @getimagesize($file->getRealPath() ?: $file->getPathname() ?: '');

                    if (is_array($imageInfo)) {
                        $width = isset($imageInfo[0]) ? (int) $imageInfo[0] : null;
                        $height = isset($imageInfo[1]) ? (int) $imageInfo[1] : null;
                    }
                }
            }

            if ($this->canReadExif($mime)) {
                $exif = $this->readExif($file);
            }
        }

        return [
            'width' => $width,
            'height' => $height,
            'duration_ms' => $duration,
            'exif' => $exif,
        ];
    }

    /**
     * Проверить, можно ли читать EXIF данные для данного MIME-типа.
     *
     * @param string $mime MIME-тип файла
     * @return bool true, если EXIF доступен для этого типа
     */
    private function canReadExif(string $mime): bool
    {
        if (! function_exists('exif_read_data')) {
            return false;
        }

        return in_array($mime, ['image/jpeg', 'image/tiff'], true);
    }

    /**
     * Прочитать EXIF данные из файла.
     *
     * Нормализует EXIF данные, оставляя только скалярные значения.
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @return array<string, array<string, mixed>>|null EXIF данные или null, если не удалось прочитать
     */
    private function readExif(UploadedFile $file): ?array
    {
        $path = $file->getRealPath();

        if (! $path || ! is_file($path)) {
            return null;
        }

        $data = @exif_read_data($path, null, true, false);

        if (! is_array($data)) {
            return null;
        }

        $normalized = [];
        foreach ($data as $section => $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $key => $value) {
                if (is_string($key) && is_scalar($value)) {
                    $normalized[$section][$key] = $value;
                }
            }
        }

        return $normalized === [] ? null : $normalized;
    }
}


