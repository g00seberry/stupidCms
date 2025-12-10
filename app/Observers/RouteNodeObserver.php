<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RouteNode;
use App\Services\DynamicRoutes\DynamicRouteCache;

/**
 * Observer для модели RouteNode.
 *
 * Обрабатывает события жизненного цикла RouteNode:
 * - Автоматическая инвалидация кэша дерева маршрутов при изменениях
 *
 * @package App\Observers
 */
class RouteNodeObserver
{
    /**
     * @param \App\Services\DynamicRoutes\DynamicRouteCache $cache Сервис кэширования маршрутов
     */
    public function __construct(
        private DynamicRouteCache $cache,
    ) {}

    /**
     * Обработать событие "saved" для RouteNode.
     *
     * Инвалидирует кэш дерева маршрутов при создании или обновлении узла.
     *
     * @param \App\Models\RouteNode $node Сохранённый узел
     * @return void
     */
    public function saved(RouteNode $node): void
    {
        $this->cache->forgetTree();
    }

    /**
     * Обработать событие "deleted" для RouteNode.
     *
     * Инвалидирует кэш дерева маршрутов при удалении узла (включая soft delete).
     *
     * @param \App\Models\RouteNode $node Удалённый узел
     * @return void
     */
    public function deleted(RouteNode $node): void
    {
        $this->cache->forgetTree();
    }

    /**
     * Обработать событие "restored" для RouteNode.
     *
     * Инвалидирует кэш дерева маршрутов при восстановлении узла из soft delete.
     *
     * @param \App\Models\RouteNode $node Восстановленный узел
     * @return void
     */
    public function restored(RouteNode $node): void
    {
        $this->cache->forgetTree();
    }
}

