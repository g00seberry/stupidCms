<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Services;

use App\Models\Plugin;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Автозагрузчик классов плагинов.
 *
 * Регистрирует PSR-4 автозагрузку для классов плагинов в Composer ClassLoader.
 *
 * @package App\Domain\Plugins\Services
 */
final class PluginAutoloader
{
    /**
     * Зарегистрировать автозагрузку для плагинов.
     *
     * Извлекает namespace из FQCN провайдера и регистрирует PSR-4 автозагрузку
     * для директории src плагина.
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\Plugin> $plugins Коллекция плагинов
     * @return void
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

