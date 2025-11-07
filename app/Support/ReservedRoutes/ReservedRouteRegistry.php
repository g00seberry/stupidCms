<?php

namespace App\Support\ReservedRoutes;

use App\Models\ReservedRoute;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;

final class ReservedRouteRegistry
{
    private const CACHE_KEY = 'reserved_routes_all';
    private const CACHE_TTL = 60; // секунды

    public function __construct(
        private CacheRepository $cache,
        private array $config
    ) {}

    /**
     * Получить все зарезервированные пути и префиксы
     * @return array{paths: string[], prefixes: string[]}
     */
    public function all(): array
    {
        return $this->cache->remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn() => $this->loadAll()
        );
    }

    /**
     * Проверка, является ли путь зарезервированным (точное совпадение)
     */
    public function isReservedPath(string $path): bool
    {
        $normalized = $this->normalize($path);
        $all = $this->all();
        
        return in_array($normalized, $all['paths'], true);
    }

    /**
     * Проверка, является ли путь зарезервированным префиксом
     */
    public function isReservedPrefix(string $path): bool
    {
        $normalized = $this->normalize($path);
        $all = $this->all();
        
        // Для slug (один сегмент) проверяем точное совпадение
        if (strpos($normalized, '/') === false) {
            return in_array($normalized, $all['prefixes'], true);
        }
        
        // Для полных путей проверяем, начинается ли с префикса
        foreach ($all['prefixes'] as $prefix) {
            if (str_starts_with($normalized, $prefix . '/')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Проверка по slug (первый сегмент, без ведущего слэша)
     */
    public function isReservedSlug(string $slug): bool
    {
        $normalized = $this->normalize($slug);
        $all = $this->all();
        
        // Проверяем как path и как prefix
        return in_array($normalized, $all['paths'], true) 
            || in_array($normalized, $all['prefixes'], true);
    }

    /**
     * Очистить кэш
     */
    public function clearCache(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * Загрузить все зарезервированные маршруты из конфига и БД
     */
    private function loadAll(): array
    {
        $paths = [];
        $prefixes = [];

        // Загружаем из конфига
        $configRoutes = $this->config['reserved_routes'] ?? [];
        if (isset($configRoutes['paths'])) {
            foreach ($configRoutes['paths'] as $path) {
                $paths[] = $this->normalize($path);
            }
        }
        if (isset($configRoutes['prefixes'])) {
            foreach ($configRoutes['prefixes'] as $prefix) {
                $prefixes[] = $this->normalize($prefix);
            }
        }

        // Загружаем из БД
        try {
            $dbRoutes = ReservedRoute::all();
            foreach ($dbRoutes as $route) {
                $normalized = $this->normalize($route->path);
                if ($route->kind === 'path') {
                    $paths[] = $normalized;
                } elseif ($route->kind === 'prefix') {
                    $prefixes[] = $normalized;
                }
            }
        } catch (\Exception $e) {
            // Если таблицы нет (например, в тестах), игнорируем
        }

        // Дедупликация и сортировка
        $paths = array_values(array_unique($paths));
        $prefixes = array_values(array_unique($prefixes));

        return [
            'paths' => $paths,
            'prefixes' => $prefixes,
        ];
    }

    /**
     * Нормализация пути: trim, lowercase, NFC
     */
    private function normalize(string $path): string
    {
        $path = trim($path, " \t\n\r\0\x0B/\\");
        
        if (extension_loaded('intl') && class_exists(\Normalizer::class)) {
            $path = \Normalizer::normalize($path, \Normalizer::FORM_C) ?: $path;
        }
        
        return mb_strtolower($path, 'UTF-8');
    }
}

