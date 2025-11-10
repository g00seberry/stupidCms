<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Admin\AdminJsonResource;
use App\Models\Plugin;

/** @mixin Plugin */
final class PluginResource extends AdminJsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
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

