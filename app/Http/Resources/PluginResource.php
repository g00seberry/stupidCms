<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Admin\AdminJsonResource;
use App\Models\Plugin;

/**
 * API Resource для Plugin в админ-панели.
 *
 * Форматирует плагин для ответа API, включая информацию о том,
 * зарегистрирован ли провайдер плагина в приложении.
 *
 * @package App\Http\Resources
 * @mixin \App\Models\Plugin
 */
final class PluginResource extends AdminJsonResource
{
    /**
     * Отключить обёртку 'data' в ответе.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Преобразовать ресурс в массив.
     *
     * Проверяет, зарегистрирован ли провайдер плагина в приложении,
     * чтобы определить, активны ли маршруты плагина.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями плагина
     */
    public function toArray($request): array
    {
        $plugin = $this->resource;

        $loadedProviders = app()->getLoadedProviders();

        return [
            'slug' => $plugin->slug,
            'name' => $plugin->name,
            'version' => $plugin->version,
            'enabled' => (bool) $plugin->enabled,
            'provider' => $plugin->provider_fqcn,
            'routes_active' => (bool) ($loadedProviders[$plugin->provider_fqcn] ?? false),
            'last_synced_at' => $plugin->last_synced_at?->toAtomString(),
        ];
    }

    // AdminJsonResource already добавляет Cache-Control/Vary
}

