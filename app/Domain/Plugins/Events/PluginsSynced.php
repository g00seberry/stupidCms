<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: плагины синхронизированы.
 *
 * Отправляется после успешной синхронизации плагинов из файловой системы в БД.
 *
 * @package App\Domain\Plugins\Events
 */
final class PluginsSynced
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param int $added Количество добавленных плагинов
     * @param int $updated Количество обновлённых плагинов
     * @param int $removed Количество удалённых плагинов
     * @param list<string> $providers Список FQCN провайдеров обнаруженных плагинов
     */
    public function __construct(
        public readonly int $added,
        public readonly int $updated,
        public readonly int $removed,
        /** @var list<string> */
        public readonly array $providers,
    ) {
    }
}

