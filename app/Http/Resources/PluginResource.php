<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plugin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plugin */
final class PluginResource extends JsonResource
{
    /**
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
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

    public function withResponse($request, $response): void
    {
        $response->header('Cache-Control', 'no-store, private');
        $response->header('Vary', 'Cookie');
    }
}

