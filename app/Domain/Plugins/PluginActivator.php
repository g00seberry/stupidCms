<?php

declare(strict_types=1);

namespace App\Domain\Plugins;

use App\Domain\Plugins\Events\PluginDisabled;
use App\Domain\Plugins\Events\PluginEnabled;
use App\Domain\Plugins\Exceptions\PluginAlreadyDisabledException;
use App\Domain\Plugins\Exceptions\PluginAlreadyEnabledException;
use App\Models\Plugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class PluginActivator
{
    public function __construct(
        private readonly Services\PluginsRouteReloader $routeReloader,
    ) {
    }

    public function enable(Plugin $plugin): Plugin
    {
        if ($plugin->enabled) {
            throw PluginAlreadyEnabledException::forSlug($plugin->slug);
        }

        $updated = DB::transaction(function () use ($plugin): Plugin {
            $plugin->forceFill(['enabled' => true])->save();

            Event::dispatch(new PluginEnabled($plugin));

            return $plugin->refresh();
        });

        $this->routeReloader->reload();

        return $updated;
    }

    public function disable(Plugin $plugin): Plugin
    {
        if (! $plugin->enabled) {
            throw PluginAlreadyDisabledException::forSlug($plugin->slug);
        }

        $updated = DB::transaction(function () use ($plugin): Plugin {
            $plugin->forceFill(['enabled' => false])->save();

            Event::dispatch(new PluginDisabled($plugin));

            return $plugin->refresh();
        });

        $this->routeReloader->reload();

        return $updated;
    }
}

