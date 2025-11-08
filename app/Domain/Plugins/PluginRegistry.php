<?php

declare(strict_types=1);

namespace App\Domain\Plugins;

use App\Models\Plugin;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class PluginRegistry
{
    /**
     * @return EloquentCollection<int, Plugin>
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
     * @return list<string>
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

