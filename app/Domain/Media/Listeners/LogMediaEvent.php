<?php

declare(strict_types=1);

namespace App\Domain\Media\Listeners;

use App\Domain\Media\Events\MediaDeleted;
use App\Domain\Media\Events\MediaProcessed;
use App\Domain\Media\Events\MediaUploaded;
use Illuminate\Support\Facades\Log;

/**
 * Слушатель для логирования событий медиа-файлов.
 *
 * Логирует все события жизненного цикла медиа-файлов:
 * - загрузка (MediaUploaded)
 * - обработка вариантов (MediaProcessed)
 * - удаление (MediaDeleted)
 *
 * @package App\Domain\Media\Listeners
 */
final class LogMediaEvent
{
    /**
     * Обработать событие загрузки медиа-файла.
     *
     * @param \App\Domain\Media\Events\MediaUploaded $event Событие загрузки
     * @return void
     */
    public function handleMediaUploaded(MediaUploaded $event): void
    {
        $media = $event->media;

        Log::info('Media file uploaded', [
            'media_id' => $media->id,
            'original_name' => $media->original_name,
            'mime' => $media->mime,
            'size_bytes' => $media->size_bytes,
            'collection' => $media->collection,
            'disk' => $media->disk,
            'path' => $media->path,
        ]);
    }

    /**
     * Обработать событие обработки медиа-файла.
     *
     * @param \App\Domain\Media\Events\MediaProcessed $event Событие обработки
     * @return void
     */
    public function handleMediaProcessed(MediaProcessed $event): void
    {
        $media = $event->media;
        $variant = $event->variant;

        Log::info('Media variant processed', [
            'media_id' => $media->id,
            'variant' => $variant->variant,
            'variant_path' => $variant->path,
            'variant_size_bytes' => $variant->size_bytes,
            'variant_width' => $variant->width,
            'variant_height' => $variant->height,
        ]);
    }

    /**
     * Обработать событие удаления медиа-файла.
     *
     * @param \App\Domain\Media\Events\MediaDeleted $event Событие удаления
     * @return void
     */
    public function handleMediaDeleted(MediaDeleted $event): void
    {
        $media = $event->media;

        Log::info('Media file deleted', [
            'media_id' => $media->id,
            'original_name' => $media->original_name,
            'mime' => $media->mime,
            'size_bytes' => $media->size_bytes,
            'collection' => $media->collection,
            'disk' => $media->disk,
            'path' => $media->path,
        ]);
    }
}

