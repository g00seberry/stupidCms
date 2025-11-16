<?php

declare(strict_types=1);

namespace App\Domain\Media\Images;

use RuntimeException;

/**
 * Реализация ImageProcessor на базе GD.
 *
 * Ограничения: отсутствует поддержка HEIC/AVIF для open/encode.
 */
final class GdImageProcessor implements ImageProcessor
{
    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException Если данные повреждены или не поддерживаются GD
     */
    public function open(string $contents): ImageRef
    {
        /** @var \GdImage|false $image */
        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new RuntimeException('Unsupported or corrupt image data for GD.');
        }

        return new ImageRef($image);
    }

    /**
     * {@inheritDoc}
     */
    public function width(ImageRef $image): int
    {
        /** @var \GdImage $gd */
        $gd = $image->native;
        return imagesx($gd);
    }

    /**
     * {@inheritDoc}
     */
    public function height(ImageRef $image): int
    {
        /** @var \GdImage $gd */
        $gd = $image->native;
        return imagesy($gd);
    }

    /**
     * {@inheritDoc}
     */
    public function resize(ImageRef $image, int $targetWidth, int $targetHeight): ImageRef
    {
        /** @var \GdImage $gd */
        $gd = $image->native;

        if (imagesx($gd) === $targetWidth && imagesy($gd) === $targetHeight) {
            return $image;
        }

        $resampled = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resampled, false);
        imagesavealpha($resampled, true);

        imagecopyresampled(
            $resampled,
            $gd,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            imagesx($gd),
            imagesy($gd)
        );

        // Освобождаем исходный
        imagedestroy($gd);

        return new ImageRef($resampled);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException Если не удалось закодировать изображение
     */
    public function encode(ImageRef $image, string $preferredExtension, int $quality = 82): array
    {
        /** @var \GdImage $gd */
        $gd = $image->native;

        $ext = strtolower($preferredExtension) ?: 'jpg';
        ob_start();
        $mime = 'image/jpeg';

        switch ($ext) {
            case 'png':
                // Уровень сжатия 0 (без) - 9 (максимум). Конвертируем качество 0-100 в 0-9.
                $compression = max(0, min(9, (int) round((100 - $quality) / 11.111)));
                imagepng($gd, null, $compression);
                $mime = 'image/png';
                break;
            case 'gif':
                imagegif($gd);
                $mime = 'image/gif';
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    imagewebp($gd, null, max(0, min(100, $quality)));
                    $mime = 'image/webp';
                    break;
                }
                // Fallback
                imagejpeg($gd, null, max(0, min(100, $quality)));
                $ext = 'jpg';
                $mime = 'image/jpeg';
                break;
            case 'jpg':
            case 'jpeg':
            default:
                imagejpeg($gd, null, max(0, min(100, $quality)));
                $ext = in_array($ext, ['jpg', 'jpeg'], true) ? $ext : 'jpg';
                $mime = 'image/jpeg';
        }

        $data = ob_get_clean();
        if ($data === false) {
            throw new RuntimeException('Failed to encode image via GD.');
        }

        return [
            'data' => $data,
            'extension' => $ext,
            'mime' => $mime,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function destroy(ImageRef $image): void
    {
        /** @var \GdImage $gd */
        $gd = $image->native;
        imagedestroy($gd);
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $extension): bool
    {
        $ext = strtolower($extension);
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }
}


