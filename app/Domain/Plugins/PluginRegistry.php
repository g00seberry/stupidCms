<?php

declare(strict_types=1);

namespace App\Domain\Plugins;

use App\Models\Plugin;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Реестр плагинов.
 *
 * Управляет списком включённых плагинов и их провайдерами.
 *
 * @package App\Domain\Plugins
 */
final class PluginRegistry
{
    /**
     * Получить все включённые плагины.
     *
     * Возвращает пустую коллекцию, если таблица plugins не существует
     * (например, до выполнения миграций).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Plugin> Коллекция включённых плагинов
     */
    public function enabled(): EloquentCollection
    {
        if (! Schema::hasTable('plugins')) {
            return new EloquentCollection();
        }

        return Plugin::query()
            ->where('enabled', true)
            ->orderBy('slug')
            ->get();
    }

    /**
     * Получить список FQCN провайдеров включённых плагинов.
     *
     * Используется для регистрации Service Providers плагинов в Laravel.
     *
     * @return list<string> Список FQCN провайдеров
     */
    public function enabledProviders(): array
    {
        return $this->enabled()
            ->pluck('provider_fqcn')
            ->filter(static fn ($provider) => is_string($provider) && $provider !== '')
            ->values()
            ->all();
    }
}

