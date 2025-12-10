<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\RouteNodeRepository;
use Illuminate\Console\Command;

/**
 * Команда для прогрева кэша динамических маршрутов.
 *
 * Загружает дерево маршрутов из базы данных и сохраняет его в кэш
 * для ускорения последующих запросов.
 *
 * @package App\Console\Commands
 */
class DynamicRoutesCacheCommand extends Command
{
    /**
     * Имя и сигнатура консольной команды.
     *
     * @var string
     */
    protected $signature = 'routes:dynamic-cache';

    /**
     * Описание консольной команды.
     *
     * @var string
     */
    protected $description = 'Warm up the cache for dynamic routes';

    /**
     * Выполнить консольную команду.
     *
     * Загружает дерево маршрутов через RouteNodeRepository,
     * что автоматически заполняет кэш.
     *
     * @param \App\Repositories\RouteNodeRepository $repository Репозиторий узлов маршрутов
     * @return int Код возврата (0 = успех, 1 = ошибка)
     */
    public function handle(RouteNodeRepository $repository): int
    {
        $this->info('Warming up dynamic routes cache...');

        try {
            $tree = $repository->getTree();
            $count = $tree->count();

            $this->info("Cache warmed up successfully. Loaded {$count} route node(s).");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to warm up cache: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}

