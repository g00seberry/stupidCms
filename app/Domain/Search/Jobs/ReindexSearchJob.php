<?php

declare(strict_types=1);

namespace App\Domain\Search\Jobs;

use App\Domain\Search\IndexManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Job для реиндексации поискового индекса в фоновом режиме.
 *
 * Выполняет полную реиндексацию всех опубликованных записей через очередь.
 *
 * @package App\Domain\Search\Jobs
 */
final class ReindexSearchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Таймаут выполнения job в секундах.
     *
     * @var int
     */
    public int $timeout = 600;

    /**
     * ID для отслеживания выполнения реиндексации.
     *
     * @var string
     */
    public readonly string $trackingId;

    /**
     * @param string|null $trackingId ID для отслеживания (генерируется автоматически, если не указан)
     */
    public function __construct(?string $trackingId = null)
    {
        $this->trackingId = $trackingId ?? (string) Str::ulid();
    }

    /**
     * Выполнить реиндексацию.
     *
     * @param \App\Domain\Search\IndexManager $indexManager Менеджер индексов
     * @return void
     */
    public function handle(IndexManager $indexManager): void
    {
        $indexManager->reindex();
    }
}


