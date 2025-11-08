<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Services;

use App\Models\Plugin;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class PluginAutoloader
{
    /**
     * @param Collection<int, Plugin> $plugins
     */
    public function register(Collection $plugins): void
    {
        /** @var ClassLoader $loader */
        $loader = require base_path('vendor/autoload.php');

        foreach ($plugins as $plugin) {
            $namespace = Str::beforeLast($plugin->provider_fqcn, '\\');

            if ($namespace === '' || $plugin->path === '') {
                continue;
            }

            $prefix = Str::finish($namespace, '\\');
            $basePath = rtrim($plugin->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;

            $loader->addPsr4($prefix, $basePath, true);
        }
    }
}

