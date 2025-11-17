<?php

declare(strict_types=1);

namespace App\Domain\Media\Events;

use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: медиа-файл обработан (сгенерирован вариант).
 *
 * Отправляется после успешной генерации варианта медиа-файла.
 * Используется для логирования, уведомлений и автоматических интеграций (CDN purge).
 *
 * @package App\Domain\Media\Events
 */
final class MediaProcessed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param \App\Models\Media $media Медиа-файл
     * @param \App\Models\MediaVariant $variant Сгенерированный вариант
     */
    public function __construct(
        public readonly Media $media,
        public readonly MediaVariant $variant,
    ) {
    }
}

