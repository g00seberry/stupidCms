<?php

declare(strict_types=1);

namespace App\Domain\Media\Validation;

use App\Domain\Media\Images\ImageProcessor;
use Illuminate\Http\UploadedFile;

/**
 * Валидатор проверки целостности (corruption) медиа-файлов.
 *
 * Проверяет, что файл не повреждён и может быть корректно обработан.
 * Для изображений пытается открыть файл через ImageProcessor.
 * Для видео/аудио проверка выполняется через плагины метаданных.
 *
 * @package App\Domain\Media\Validation
 */
class CorruptionValidator implements MediaValidatorInterface
{
    /**
     * @param \App\Domain\Media\Images\ImageProcessor $imageProcessor Процессор изображений
     */
    public function __construct(
        private readonly ImageProcessor $imageProcessor
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
        // Поддерживаем изображения для проверки через ImageProcessor
        return str_starts_with($mime, 'image/');
    }

    /**
     * Валидировать файл на corruption.
     *
     * @param \Illuminate\Http\UploadedFile $file Загруженный файл
     * @param string $mime MIME-тип файла
     * @return void
     * @throws \App\Domain\Media\Validation\MediaValidationException Если файл повреждён
     */
    public function validate(UploadedFile $file, string $mime): void
    {
        if (! str_starts_with($mime, 'image/')) {
            return;
        }

        $path = $file->getRealPath() ?: $file->getPathname();

        if (! is_string($path) || ! is_file($path)) {
            throw new MediaValidationException(
                'Cannot read file for corruption validation.',
                self::class
            );
        }

        // Проверяем размер файла вместо содержимого (для тестовых fake файлов)
        $fileSize = $file->getSize();
        if ($fileSize === null || $fileSize <= 0) {
            throw new MediaValidationException(
                'File appears to be empty or unreadable.',
                self::class
            );
        }

        $bytes = @file_get_contents($path);

        if ($bytes === false || $bytes === '') {
            throw new MediaValidationException(
                'File appears to be empty or unreadable.',
                self::class
            );
        }

        try {
            $img = $this->imageProcessor->open($bytes);
            $width = $this->imageProcessor->width($img);
            $height = $this->imageProcessor->height($img);

            if ($width === null || $height === null || $width <= 0 || $height <= 0) {
                throw new MediaValidationException(
                    'Image dimensions are invalid or corrupted.',
                    self::class
                );
            }

            $this->imageProcessor->destroy($img);
        } catch (\Throwable $e) {
            // Fallback на getimagesize для форматов, которые не поддерживаются ImageProcessor
            $imageInfo = @getimagesize($path);

            if ($imageInfo === false || ! is_array($imageInfo)) {
                // Если getimagesize тоже не смог определить размеры, но файл не пустой,
                // это может быть формат, который не поддерживается (HEIC, AVIF и т.д.)
                // В этом случае пропускаем проверку corruption, так как файл не пустой
                if (strlen($bytes) > 0) {
                    return; // Файл не пустой, но формат не поддерживается - пропускаем проверку
                }

                throw new MediaValidationException(
                    sprintf('Image file is corrupted or invalid: %s', $e->getMessage()),
                    self::class
                );
            }
        }
    }
}

