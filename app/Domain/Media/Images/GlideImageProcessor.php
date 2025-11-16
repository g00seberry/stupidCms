<?php

declare(strict_types=1);

namespace App\Domain\Media\Images;

use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

/**
 * Реализация ImageProcessor на базе Intervention Image (как backend для Glide-стека).
 *
 * Поддержка форматов зависит от выбранного драйвера (gd/imagick).
 * Для AVIF/HEIC нужен imagick с соответствующими кодеками.
 */
final class GlideImageProcessor implements ImageProcessor
{
    public function __construct(
        private readonly ImageManager $manager
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException Если данные повреждены или не поддерживаются драйвером
     */
    public function open(string $contents): ImageRef
    {
        try {
            $img = $this->manager->read($contents);
        } catch (\Throwable $e) {
            throw new RuntimeException('Unsupported or corrupt image data for Glide/Intervention.', previous: $e);
        }

        return new ImageRef($img);
    }

    /**
     * {@inheritDoc}
     */
    public function width(ImageRef $image): int
    {
        /** @var ImageInterface $im */
        $im = $image->native;
        return $im->width();
    }

    /**
     * {@inheritDoc}
     */
    public function height(ImageRef $image): int
    {
        /** @var ImageInterface $im */
        $im = $image->native;
        return $im->height();
    }

    /**
     * {@inheritDoc}
     */
    public function resize(ImageRef $image, int $targetWidth, int $targetHeight): ImageRef
    {
        /** @var ImageInterface $im */
        $im = $image->native;

        if ($im->width() === $targetWidth && $im->height() === $targetHeight) {
            return $image;
        }

        // Масштабирование до точных размеров без обрезки, с сохранением альфы
        // В Intervention Image v3 доступен метод scale(width, height)
        $resized = $im->scale($targetWidth, $targetHeight);

        return new ImageRef($resized);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException Если кодирование завершилось пустыми данными
     */
    public function encode(ImageRef $image, string $preferredExtension, int $quality = 82): array
    {
        /** @var ImageInterface $im */
        $im = $image->native;

        $ext = strtolower($preferredExtension) ?: 'jpg';
        $q = max(0, min(100, $quality));

        // Подбираем метод кодирования
        switch ($ext) {
            case 'png':
                $data = (string) $im->toPng();
                $mime = 'image/png';
                break;
            case 'gif':
                $data = (string) $im->toGif();
                $mime = 'image/gif';
                break;
            case 'webp':
                $data = (string) $im->toWebp(quality: $q);
                $mime = 'image/webp';
                break;
            case 'avif':
                // Может бросить исключение при отсутствии поддержки
                try {
                    $data = (string) $im->toAvif(quality: $q);
                    $mime = 'image/avif';
                    break;
                } catch (\Throwable) {
                    // Fallback
                    $data = (string) $im->toJpeg(quality: $q);
                    $ext = 'jpg';
                    $mime = 'image/jpeg';
                    break;
                }
            case 'heic':
            case 'heif':
                try {
                    // В некоторых сборках доступно через toHeic()
                    if (method_exists($im, 'toHeic')) {
                        /** @phpstan-ignore-next-line */
                        $data = (string) $im->toHeic(quality: $q);
                        $mime = 'image/heic';
                        $ext = 'heic';
                        break;
                    }
                    // Если нет прямой поддержки — fallback
                    $data = (string) $im->toJpeg(quality: $q);
                    $ext = 'jpg';
                    $mime = 'image/jpeg';
                } catch (\Throwable) {
                    $data = (string) $im->toJpeg(quality: $q);
                    $ext = 'jpg';
                    $mime = 'image/jpeg';
                }
                break;
            case 'jpg':
            case 'jpeg':
            default:
                $data = (string) $im->toJpeg(quality: $q);
                $ext = in_array($ext, ['jpg', 'jpeg'], true) ? $ext : 'jpg';
                $mime = 'image/jpeg';
        }

        if ($data === '') {
            throw new RuntimeException('Failed to encode image via Glide/Intervention.');
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
        // Intervention Image объекты — управляемые GC, явной очистки не требуется
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $extension): bool
    {
        $ext = strtolower($extension);
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'heif'], true);
    }
}


