<?php

declare(strict_types=1);

namespace App\Domain\Media\Listeners;

use App\Domain\Media\Events\MediaDeleted;
use App\Domain\Media\Events\MediaProcessed;
use App\Domain\Media\Events\MediaUploaded;
use Illuminate\Support\Facades\Log;

/**
 * Слушатель для отправки уведомлений о событиях медиа-файлов.
 *
 * Отправляет уведомления при событиях жизненного цикла медиа-файлов.
 * Может быть расширен для интеграции с email, Slack, webhooks и т.д.
 *
 * @package App\Domain\Media\Listeners
 */
final class NotifyMediaEvent
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

        // TODO: Реализовать отправку уведомлений (email, Slack, webhook и т.д.)
        // Пример: отправка уведомления администраторам при загрузке больших файлов
        if ($media->size_bytes > (1024 * 1024 * 10)) { // > 10MB
            Log::info('Large media file uploaded, notification should be sent', [
                'media_id' => $media->id,
                'size_bytes' => $media->size_bytes,
            ]);
        }
    }

    /**
     * Обработать событие обработки медиа-файла.
     *
     * @param \App\Domain\Media\Events\MediaProcessed $event Событие обработки
     * @return void
     */
    public function handleMediaProcessed(MediaProcessed $event): void
    {
        // TODO: Реализовать отправку уведомлений при завершении обработки вариантов
        // Пример: уведомление пользователя о готовности варианта
    }

    /**
     * Обработать событие удаления медиа-файла.
     *
     * @param \App\Domain\Media\Events\MediaDeleted $event Событие удаления
     * @return void
     */
    public function handleMediaDeleted(MediaDeleted $event): void
    {
        // TODO: Реализовать отправку уведомлений при удалении медиа-файлов
        // Пример: уведомление администраторов о критических удалениях
    }
}

