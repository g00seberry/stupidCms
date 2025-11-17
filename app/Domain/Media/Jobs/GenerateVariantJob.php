<?php

declare(strict_types=1);

namespace App\Domain\Media\Jobs;

use App\Domain\Media\Services\OnDemandVariantService;
use App\Models\Media;
use App\Models\MediaVariant;
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
     * Кол-во попыток и задержка между ретраями.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * Бэкофф между попытками (секунды).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 60];
    }

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
     * Событие MediaProcessed отправляется автоматически из OnDemandVariantService::generateVariant.
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

        // Помечаем вариант как processing/started
        $variant = MediaVariant::updateOrCreate(
            ['media_id' => $this->mediaId, 'variant' => $this->variant],
            [
                'status' => \App\Domain\Media\MediaVariantStatus::Processing,
                'error_message' => null,
                'started_at' => now('UTC'),
            ]
        );

        $service->generateVariant($media, $this->variant);
    }
}


