<?php

declare(strict_types=1);

namespace App\Domain\Media\Validation;

use Illuminate\Http\UploadedFile;

/**
 * Валидатор ограничений размера файла и размеров изображений/видео.
 *
 * Проверяет, что размер файла и размеры контента (ширина/высота для изображений,
 * длительность для видео/аудио) не превышают установленные лимиты.
 *
 * @package App\Domain\Media\Validation
 */
class SizeLimitValidator implements MediaValidatorInterface
{
    /**
     * @param array<string, mixed> $rules Правила ограничений (max_size_bytes, max_width, max_height, max_duration_ms)
     */
    public function __construct(
        private readonly array $rules = []
    ) {
    }

    /**
     * Проверить, поддерживает ли валидатор указанный MIME-тип.
     *
     * @param string $mime MIME-тип файла
     * @return bool true, если валидатор может обработать файл
     */
    public function supports(string $mime): bool
    {
        return true;
    }

    /**
     * Валидировать файл на соответствие ограничениям размера.
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @param string $mime MIME-тип файла
     * @return void
     * @throws \App\Domain\Media\Validation\MediaValidationException Если превышены ограничения
     */
    public function validate(UploadedFile $file, string $mime): void
    {
        $sizeBytes = (int) ($file->getSize() ?? 0);

        if (isset($this->rules['max_size_bytes']) && is_int($this->rules['max_size_bytes'])) {
            if ($sizeBytes > $this->rules['max_size_bytes']) {
                throw new MediaValidationException(
                    sprintf(
                        'File size (%d bytes) exceeds maximum allowed size (%d bytes).',
                        $sizeBytes,
                        $this->rules['max_size_bytes']
                    ),
                    self::class
                );
            }
        }

        // Для изображений проверяем размеры
        if (str_starts_with($mime, 'image/')) {
            $this->validateImageDimensions($file, $mime);
        }

        // Для видео/аудио проверяем длительность (если доступна)
        if (str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/')) {
            // Длительность будет проверена после извлечения метаданных
            // Здесь только проверяем размер файла
        }
    }

    /**
     * Валидировать размеры изображения.
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @param string $mime MIME-тип файла
     * @return void
     * @throws \App\Domain\Media\Validation\MediaValidationException Если превышены ограничения размеров
     */
    private function validateImageDimensions(UploadedFile $file, string $mime): void
    {
        $path = $file->getRealPath() ?: $file->getPathname();

        if (! is_string($path) || ! is_file($path)) {
            return;
        }

        $imageInfo = @getimagesize($path);

        if ($imageInfo === false || ! is_array($imageInfo)) {
            return; // Не удалось определить размеры, пропускаем проверку
        }

        $width = isset($imageInfo[0]) ? (int) $imageInfo[0] : null;
        $height = isset($imageInfo[1]) ? (int) $imageInfo[1] : null;

        if ($width !== null && isset($this->rules['max_width']) && is_int($this->rules['max_width'])) {
            if ($width > $this->rules['max_width']) {
                throw new MediaValidationException(
                    sprintf(
                        'Image width (%d px) exceeds maximum allowed width (%d px).',
                        $width,
                        $this->rules['max_width']
                    ),
                    self::class
                );
            }
        }

        if ($height !== null && isset($this->rules['max_height']) && is_int($this->rules['max_height'])) {
            if ($height > $this->rules['max_height']) {
                throw new MediaValidationException(
                    sprintf(
                        'Image height (%d px) exceeds maximum allowed height (%d px).',
                        $height,
                        $this->rules['max_height']
                    ),
                    self::class
                );
            }
        }
    }
}

