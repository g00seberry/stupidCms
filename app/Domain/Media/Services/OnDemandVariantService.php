<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Events\MediaProcessed;
use App\Domain\Media\Images\ImageProcessor;
use App\Domain\Media\Images\ImageRef;
use App\Domain\Media\Jobs\GenerateVariantJob;
use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Сервис для генерации вариантов медиа-файлов по требованию.
 *
 * Генерирует варианты изображений (thumbnails, resized) на лету
 * через абстракцию ImageProcessor (gd/imagick/glide/external).
 *
 * @package App\Domain\Media\Services
 */
class OnDemandVariantService
{
    /**
     * @param \App\Domain\Media\Images\ImageProcessor $images Процессор изображений
     */
    public function __construct(
        private readonly ImageProcessor $images
    ) {
    }

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

        // Переводим генерацию в асинхронный пайплайн.
        // Для sync-драйвера очереди и в тестах выполняем синхронно без очереди.
        if (config('queue.default') === 'sync' || app()->runningUnitTests()) {
            return $this->generateVariant($media, $variant);
        } else {
            GenerateVariantJob::dispatch($media->id, $variant);
        }

        return MediaVariant::where('media_id', $media->id)
            ->where('variant', $variant)
            ->firstOrFail();
    }

    /**
     * Сгенерировать вариант медиа-файла.
     *
     * Читает оригинальный файл, изменяет размер (если нужно),
     * кодирует в нужный формат и сохраняет на диск.
     * После успешной генерации отправляет событие MediaProcessed.
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
        $variantFormat = isset($config['format']) ? (string) $config['format'] : null;
        $variantQuality = isset($config['quality']) ? (int) $config['quality'] : null;

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

        $image = $this->images->open($contents);

        $originalWidth = $this->images->width($image);
        $originalHeight = $this->images->height($image);

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

        $resized = $this->images->resize($image, $targetWidth, $targetHeight);

        $preferredExt = $variantFormat ?: ($media->ext ?? pathinfo($media->path, PATHINFO_EXTENSION));
        $quality = $variantQuality ?? (int) (config('media.image.quality', 82));
        $encoded = $this->images->encode($resized, (string) $preferredExt, $quality);
        $variantPath = $this->buildVariantPath($media, $variant, $encoded['extension']);

        $disk->put($variantPath, $encoded['data']);

        $sizeBytes = $disk->size($variantPath);

        // Обновляем/создаём запись варианта и фиксируем прогресс
        $variantModel = MediaVariant::updateOrCreate(
            ['media_id' => $media->id, 'variant' => $variant],
            [
                'path' => $variantPath,
                'width' => $this->images->width($resized),
                'height' => $this->images->height($resized),
                'size_bytes' => $sizeBytes ?: strlen($encoded['data']),
                'status' => \App\Domain\Media\MediaVariantStatus::Ready,
                'error_message' => null,
                'finished_at' => now('UTC'),
            ]
        );

        $this->images->destroy($resized);

        // Отправляем событие обработки медиа-файла
        Event::dispatch(new MediaProcessed($media, $variantModel));

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


