<?php

declare(strict_types=1);

namespace App\Domain\Media\Events;

use App\Models\Media;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: медиа-файл загружен.
 *
 * Отправляется после успешной загрузки и сохранения медиа-файла в БД.
 * Используется для логирования, уведомлений и автоматических интеграций (CDN purge).
 *
 * @package App\Domain\Media\Events
 */
final class MediaUploaded
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param \App\Models\Media $media Загруженный медиа-файл
     */
    public function __construct(
        public readonly Media $media,
    ) {
    }
}

