<?php

declare(strict_types=1);

namespace App\Domain\Media\Jobs;

use App\Domain\Media\Services\OnDemandVariantService;
use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job для генерации варианта медиа-файла.
 *
 * Выполняет генерацию варианта изображения (thumbnail, resize и т.д.)
 * в фоновом режиме через очередь.
 *
 * @package App\Domain\Media\Jobs
 */
class GenerateVariantJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param string $mediaId ID медиа-файла
     * @param string $variant Имя варианта (thumbnail, medium и т.д.)
     */
    public function __construct(
        private readonly string $mediaId,
        private readonly string $variant
    ) {
    }

    /**
     * Выполнить генерацию варианта.
     *
     * Загружает медиа-файл (включая удалённые) и генерирует вариант.
     * Если медиа-файл не найден, job завершается без ошибки.
     *
     * @param \App\Domain\Media\Services\OnDemandVariantService $service Сервис для генерации вариантов
     * @return void
     */
    public function handle(OnDemandVariantService $service): void
    {
        $media = Media::withTrashed()->find($this->mediaId);

        if (! $media) {
            return;
        }

        $service->generateVariant($media, $this->variant);
    }
}


