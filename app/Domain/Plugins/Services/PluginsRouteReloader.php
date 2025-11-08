<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Services;

use App\Domain\Plugins\Events\PluginsRoutesReloaded;
use App\Domain\Plugins\Exceptions\RoutesReloadFailed;
use App\Domain\Plugins\PluginRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Throwable;

final class PluginsRouteReloader
{
    public function __construct(
        private readonly Application $app,
        private readonly PluginRegistry $registry,
        private readonly PluginAutoloader $autoloader,
    ) {
    }

    public function reload(): void
    {
        try {
            Artisan::call('route:clear');

            $plugins = $this->registry->enabled();

            $this->autoloader->register($plugins);

            foreach ($plugins as $plugin) {
                $this->app->register($plugin->provider_fqcn, true);
            }

            if (config('plugins.auto_route_cache')) {
                Artisan::call('route:cache');
            }

            Event::dispatch(new PluginsRoutesReloaded(
                $plugins->pluck('provider_fqcn')->filter()->values()->all()
            ));
        } catch (Throwable $exception) {
            report($exception);

            throw RoutesReloadFailed::from($exception);
        }
    }
}

