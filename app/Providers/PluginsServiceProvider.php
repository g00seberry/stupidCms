<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Plugins\PluginRegistry;
use App\Domain\Plugins\Services\PluginAutoloader;
use Illuminate\Support\ServiceProvider;

final class PluginsServiceProvider extends ServiceProvider
{
    public function boot(PluginRegistry $registry, PluginAutoloader $autoloader): void
    {
        $plugins = $registry->enabled();

        $autoloader->register($plugins);

        foreach ($plugins as $plugin) {
            $this->app->register($plugin->provider_fqcn);
        }
    }
}

