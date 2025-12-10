<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Сервис для кэширования дерева динамических маршрутов.
 *
 * Предоставляет методы для кэширования и инвалидации дерева маршрутов.
 * Использует версионирование ключей для возможности инвалидации при изменении схемы.
 *
 * @package App\Services\DynamicRoutes
 */
class DynamicRouteCache
{
    /**
     * Получить ключ кэша для дерева маршрутов.
     *
     * Формат: {prefix}:tree:v{version}
     *
     * @return string Ключ кэша
     */
    private function getCacheKey(): string
    {
        $prefix = config('dynamic-routes.cache_key_prefix', 'dynamic_routes');
        $version = 'v1'; // Версия для инвалидации при изменении схемы

        return "{$prefix}:tree:{$version}";
    }

    /**
     * Получить TTL кэша в секундах.
     *
     * @return int TTL в секундах
     */
    private function getCacheTtl(): int
    {
        return (int) config('dynamic-routes.cache_ttl', 3600);
    }

    /**
     * Получить дерево маршрутов из кэша или выполнить callback.
     *
     * Если дерево есть в кэше, возвращает его. Иначе выполняет callback,
     * сохраняет результат в кэш и возвращает его.
     *
     * @param callable(): \Illuminate\Database\Eloquent\Collection<int, \App\Models\RouteNode> $callback Callback для получения дерева
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\RouteNode> Дерево маршрутов
     */
    public function rememberTree(callable $callback): Collection
    {
        $key = $this->getCacheKey();
        $ttl = $this->getCacheTtl();

        return Cache::remember($key, $ttl, function () use ($callback) {
            return $callback();
        });
    }

    /**
     * Очистить кэш дерева маршрутов.
     *
     * Удаляет закэшированное дерево маршрутов для принудительного обновления.
     *
     * @return void
     */
    public function forgetTree(): void
    {
        $key = $this->getCacheKey();
        Cache::forget($key);
    }
}

