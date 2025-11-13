<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Jobs\GenerateVariantJob;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Сервис для генерации вариантов медиа-файлов по требованию.
 *
 * Генерирует варианты изображений (thumbnails, resized) на лету,
 * используя GD для обработки изображений.
 *
 * @package App\Domain\Media\Services
 */
class OnDemandVariantService
{
    /**
     * Убедиться, что вариант существует на диске и в БД.
     *
     * Проверяет существование варианта, если отсутствует — генерирует синхронно.
     *
     * @param \App\Models\Media $media Медиа-файл
     * @param string $variant Имя варианта
     * @return \App\Models\MediaVariant Созданный или существующий вариант
     * @throws \InvalidArgumentException Если медиа не поддерживает варианты или вариант не настроен
     */
    public function ensureVariant(Media $media, string $variant): MediaVariant
    {
        $this->assertSupportsVariants($media, $variant);

        $existing = $media->variants()
            ->where('variant', $variant)
            ->first();

        if ($existing && Storage::disk($media->disk)->exists($existing->path)) {
            return $existing;
        }

        GenerateVariantJob::dispatchSync($media->id, $variant);

        return $media->variants()
            ->where('variant', $variant)
            ->firstOrFail();
    }

    /**
     * Сгенерировать вариант медиа-файла.
     *
     * Читает оригинальный файл, изменяет размер (если нужно),
     * кодирует в нужный формат и сохраняет на диск.
     *
     * @param \App\Models\Media $media Медиа-файл
     * @param string $variant Имя варианта
     * @return \App\Models\MediaVariant Созданный вариант
     * @throws \InvalidArgumentException Если медиа не поддерживает варианты или вариант не настроен
     * @throws \RuntimeException Если не удалось прочитать/обработать файл
     */
    public function generateVariant(Media $media, string $variant): MediaVariant
    {
        $this->assertSupportsVariants($media, $variant);

        $config = config("media.variants.{$variant}");
        $targetMax = (int) ($config['max'] ?? 0);

        $disk = Storage::disk($media->disk);

        $stream = $disk->readStream($media->path);

        if (! $stream) {
            throw new RuntimeException('Failed to read original media for variant generation.');
        }

        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new RuntimeException('Unable to load media contents for variant generation.');
        }

        $image = @imagecreatefromstring($contents);

        if (! $image) {
            throw new RuntimeException('Unsupported image data for variant generation.');
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        $targetWidth = $originalWidth;
        $targetHeight = $originalHeight;

        if ($targetMax > 0) {
            $longSide = max($originalWidth, $originalHeight);

            if ($longSide > $targetMax) {
                $scale = $targetMax / $longSide;
                $targetWidth = max(1, (int) round($originalWidth * $scale));
                $targetHeight = max(1, (int) round($originalHeight * $scale));
            }
        }

        $resized = $this->resizeImage($image, $targetWidth, $targetHeight);

        [$encoded, $extension] = $this->encodeImage($resized, $media->ext ?? pathinfo($media->path, PATHINFO_EXTENSION));
        $variantPath = $this->buildVariantPath($media, $variant, $extension);

        $disk->put($variantPath, $encoded);

        $sizeBytes = $disk->size($variantPath);

        $variantModel = MediaVariant::updateOrCreate(
            ['media_id' => $media->id, 'variant' => $variant],
            [
                'path' => $variantPath,
                'width' => imagesx($resized),
                'height' => imagesy($resized),
                'size_bytes' => $sizeBytes ?: strlen($encoded),
            ]
        );

        imagedestroy($resized);

        return $variantModel;
    }

    /**
     * Проверить, что медиа поддерживает варианты и вариант настроен.
     *
     * @param \App\Models\Media $media Медиа-файл
     * @param string $variant Имя варианта
     * @return void
     * @throws \InvalidArgumentException Если медиа не изображение или вариант не настроен
     */
    private function assertSupportsVariants(Media $media, string $variant): void
    {
        if ($media->kind() !== 'image') {
            throw new InvalidArgumentException('Variants supported only for images.');
        }

        $variants = config('media.variants', []);

        if (! array_key_exists($variant, $variants)) {
            throw new InvalidArgumentException("Variant [{$variant}] is not configured.");
        }
    }

    /**
     * Изменить размер изображения.
     *
     * Использует imagecopyresampled для качественного изменения размера
     * с сохранением прозрачности.
     *
     * @param \GdImage $image Исходное изображение
     * @param int $targetWidth Целевая ширина
     * @param int $targetHeight Целевая высота
     * @return \GdImage Изменённое изображение
     */
    private function resizeImage(\GdImage $image, int $targetWidth, int $targetHeight): \GdImage
    {
        if (imagesx($image) === $targetWidth && imagesy($image) === $targetHeight) {
            return $image;
        }

        $resampled = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($resampled, false);
        imagesavealpha($resampled, true);

        imagecopyresampled(
            $resampled,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            imagesx($image),
            imagesy($image)
        );

        imagedestroy($image);

        return $resampled;
    }

    /**
     * Закодировать изображение в нужный формат.
     *
     * Поддерживает PNG, GIF, WebP (с fallback на JPEG) и JPEG.
     *
     * @param \GdImage $image Изображение для кодирования
     * @param string|null $extension Желаемое расширение
     * @return array{0: string, 1: string} Массив [данные, расширение]
     * @throws \RuntimeException Если не удалось закодировать изображение
     */
    private function encodeImage(\GdImage $image, ?string $extension): array
    {
        $extension = strtolower((string) $extension);
        $extension = $extension !== '' ? $extension : 'jpg';

        ob_start();

        switch ($extension) {
            case 'png':
                imagepng($image);
                break;
            case 'gif':
                imagegif($image);
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    imagewebp($image, null, 90);
                    break;
                }
                // Fallback to jpeg if webp not supported
                imagejpeg($image, null, 90);
                $extension = 'jpg';
                break;
            default:
                imagejpeg($image, null, 90);
                $extension = 'jpg';
        }

        $data = ob_get_clean();

        if ($data === false) {
            throw new RuntimeException('Failed to encode variant image.');
        }

        return [$data, $extension];
    }

    /**
     * Построить путь для варианта.
     *
     * Формат: {original-filename}-{variant}.{extension}
     *
     * @param \App\Models\Media $media Медиа-файл
     * @param string $variant Имя варианта
     * @param string $extension Расширение файла
     * @return string Путь к варианту
     */
    private function buildVariantPath(Media $media, string $variant, string $extension): string
    {
        $pathInfo = pathinfo($media->path);
        $directory = $pathInfo['dirname'] ?? '';
        $originalExtension = strtolower($pathInfo['extension'] ?? $media->ext ?? 'jpg');
        $filename = $pathInfo['filename'] ?? basename($media->path, '.'.$originalExtension);

        $variantFilename = "{$filename}-{$variant}.{$extension}";

        return ltrim($directory === '.' ? $variantFilename : "{$directory}/{$variantFilename}", '/');
    }
}


