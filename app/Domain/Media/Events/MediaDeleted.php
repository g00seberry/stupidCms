<?php

declare(strict_types=1);

namespace App\Domain\Media\Events;

use App\Models\Media;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: медиа-файл удалён.
 *
 * Отправляется после мягкого удаления (soft delete) медиа-файла.
 * Используется для логирования, уведомлений и автоматических интеграций (CDN purge).
 *
 * @package App\Domain\Media\Events
 */
final class MediaDeleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param \App\Models\Media $media Удалённый медиа-файл
     */
    public function __construct(
        public readonly Media $media,
    ) {
    }
}

