<?php

declare(strict_types=1);

namespace App\Jobs\Blueprint;

use App\Models\Blueprint;
use App\Models\Entry;
use App\Services\Entry\EntryIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: асинхронная реиндексация всех Entry blueprint'а.
 *
 * Используется при изменении структуры blueprint.
 */
class ReindexBlueprintEntries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Количество попыток выполнения job.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Таймаут выполнения (секунды).
     *
     * @var int
     */
    public $timeout = 600; // 10 минут

    /**
     * @param int $blueprintId ID blueprint для реиндексации
     */
    public function __construct(
        public int $blueprintId
    ) {}

    /**
     * Выполнить job.
     *
     * @param EntryIndexer $indexer
     * @return void
     */
    public function handle(EntryIndexer $indexer): void
    {
        $blueprint = Blueprint::find($this->blueprintId);

        if (!$blueprint) {
            Log::error("Blueprint {$this->blueprintId} не найден при реиндексации");
            return;
        }

        Log::info("Начало реиндексации Entry для blueprint '{$blueprint->code}' (ID: {$blueprint->id})");

        // Найти все PostType, использующие этот blueprint
        $postTypeIds = \App\Models\PostType::query()
            ->where('blueprint_id', $blueprint->id)
            ->pluck('id');

        if ($postTypeIds->isEmpty()) {
            Log::info("Нет PostType для blueprint '{$blueprint->code}', реиндексация пропущена");
            return;
        }

        // Реиндексировать Entry батчами
        $totalProcessed = 0;

        Entry::query()
            ->whereIn('post_type_id', $postTypeIds)
            ->chunk(100, function ($entries) use ($indexer, &$totalProcessed) {
                foreach ($entries as $entry) {
                    try {
                        $indexer->index($entry);
                        $totalProcessed++;
                    } catch (\Exception $e) {
                        Log::error("Ошибка индексации Entry {$entry->id}: {$e->getMessage()}", [
                            'exception' => $e,
                        ]);
                    }
                }
            });

        Log::info("Реиндексация blueprint '{$blueprint->code}' завершена: обработано {$totalProcessed} Entry");
    }

    /**
     * Обработать ошибку выполнения job.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Ошибка реиндексации blueprint {$this->blueprintId}: {$exception->getMessage()}", [
            'exception' => $exception,
        ]);
    }
}
