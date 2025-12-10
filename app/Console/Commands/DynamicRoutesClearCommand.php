<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DynamicRoutes\DynamicRouteCache;
use Illuminate\Console\Command;

/**
 * Команда для очистки кэша динамических маршрутов.
 *
 * Удаляет закэшированное дерево маршрутов, что приведёт к перезагрузке
 * данных из базы данных при следующем запросе.
 *
 * @package App\Console\Commands
 */
class DynamicRoutesClearCommand extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'routes:dynamic-clear';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Clear the cache for dynamic routes';

    /**
     * Выполнить консольную команду.
     *
     * Очищает кэш дерева маршрутов через DynamicRouteCache.
     *
     * @param \App\Services\DynamicRoutes\DynamicRouteCache $cache Сервис кэширования маршрутов
     * @return int Код возврата (0 = успех, 1 = ошибка)
     */
    public function handle(DynamicRouteCache $cache): int
    {
        $this->info('Clearing dynamic routes cache...');

        try {
            $cache->forgetTree();

            $this->info('Cache cleared successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to clear cache: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}

