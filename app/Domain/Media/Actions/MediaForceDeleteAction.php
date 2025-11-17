<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\Media\Events\MediaDeleted;
use App\Models\Media;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Действие для окончательного удаления медиа-файла.
 *
 * Выполняет полное (hard) удаление медиа-файла: удаляет физические файлы
 * (основной файл и все варианты) с диска, затем удаляет записи из БД.
 * Отправляет событие MediaDeleted после успешного удаления.
 *
 * @package App\Domain\Media\Actions
 */
class MediaForceDeleteAction
{
    /**
     * Выполнить окончательное удаление медиа-файла.
     *
     * Удаляет физические файлы с диска (основной файл и все варианты),
     * затем выполняет forceDelete для записи Media в БД.
     * Варианты удаляются автоматически через cascadeOnDelete.
     *
     * @param \App\Models\Media $media Медиа-файл для удаления
     * @return void
     * @throws \RuntimeException Если не удалось удалить физические файлы
     */
    public function execute(Media $media): void
    {
        $disk = Storage::disk($media->disk);

        // Загружаем варианты перед удалением
        $media->load('variants');

        // Удаление всех вариантов
        foreach ($media->variants as $variant) {
            if ($disk->exists($variant->path)) {
                if (! $disk->delete($variant->path)) {
                    throw new RuntimeException(
                        sprintf('Failed to delete variant file: %s', $variant->path)
                    );
                }
            }
        }

        // Удаление основного файла
        if ($disk->exists($media->path)) {
            if (! $disk->delete($media->path)) {
                throw new RuntimeException(
                    sprintf('Failed to delete media file: %s', $media->path)
                );
            }
        }

        // Сохраняем данные для события перед удалением
        $mediaData = $media->getAttributes();
        $mediaData['id'] = $media->id;

        // Окончательное удаление из БД (варианты удалятся через cascadeOnDelete)
        $media->forceDelete();

        // Создаём временный экземпляр для события
        $mediaForEvent = new Media();
        $mediaForEvent->setRawAttributes($mediaData);
        $mediaForEvent->exists = false;

        // Отправляем событие удаления
        Event::dispatch(new MediaDeleted($mediaForEvent));
    }
}

