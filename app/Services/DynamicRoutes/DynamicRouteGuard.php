<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes;

use Illuminate\Support\Facades\Log;

/**
 * Сервис для проверки безопасности динамических маршрутов.
 *
 * Проверяет разрешённость middleware, контроллеров и префиксов URI
 * согласно конфигурации dynamic-routes.
 *
 * @package App\Services\DynamicRoutes
 */
class DynamicRouteGuard
{
    /**
     * Проверить, разрешён ли middleware.
     *
     * Поддерживает параметризованные middleware через паттерны:
     * - 'can:*' разрешает все middleware вида can:action,Model
     * - 'throttle:*' разрешает все middleware вида throttle:60,1
     *
     * @param string $middleware Имя middleware для проверки
     * @return bool true если разрешён, false иначе
     */
    public function isMiddlewareAllowed(string $middleware): bool
    {
        $allowed = config('dynamic-routes.allowed_middleware', []);

        // Точное совпадение
        if (in_array($middleware, $allowed, true)) {
            return true;
        }

        // Проверка параметризованных middleware
        foreach ($allowed as $pattern) {
            if (str_ends_with($pattern, ':*')) {
                $prefix = substr($pattern, 0, -2); // Убираем ':*'
                if (str_starts_with($middleware, $prefix . ':')) {
                    return true;
                }
            }
        }

        // Неразрешённый middleware
        Log::warning('Dynamic route: неразрешённый middleware', [
            'middleware' => $middleware,
            'allowed' => $allowed,
        ]);

        return false;
    }

    /**
     * Проверить, разрешён ли контроллер.
     *
     * Поддерживает wildcard паттерны:
     * - 'App\Http\Controllers\*' разрешает все контроллеры из этого namespace
     *
     * @param string $controller Полное имя контроллера (namespace + класс)
     * @return bool true если разрешён, false иначе
     */
    public function isControllerAllowed(string $controller): bool
    {
        $allowed = config('dynamic-routes.allowed_controllers', []);

        foreach ($allowed as $pattern) {
            // Точное совпадение
            if ($pattern === $controller) {
                return true;
            }

            // Wildcard паттерн (например, 'App\Http\Controllers\*')
            if (str_ends_with($pattern, '*')) {
                $prefix = substr($pattern, 0, -1); // Убираем '*'
                if (str_starts_with($controller, $prefix)) {
                    return true;
                }
            }
        }

        // Неразрешённый контроллер
        Log::warning('Dynamic route: неразрешённый контроллер', [
            'controller' => $controller,
            'allowed' => $allowed,
        ]);

        return false;
    }

    /**
     * Проверить, зарезервирован ли префикс URI.
     *
     * @param string $prefix Префикс URI для проверки
     * @return bool true если зарезервирован (запрещён), false иначе
     */
    public function isPrefixReserved(string $prefix): bool
    {
        $reserved = config('dynamic-routes.reserved_prefixes', []);

        // Проверяем точное совпадение
        if (in_array($prefix, $reserved, true)) {
            return true;
        }

        // Проверяем, начинается ли префикс с зарезервированного
        foreach ($reserved as $reservedPrefix) {
            if (str_starts_with($prefix, $reservedPrefix . '/') || $prefix === $reservedPrefix) {
                return true;
            }
        }

        return false;
    }

    /**
     * Отфильтровать неразрешённые middleware из массива.
     *
     * Возвращает только разрешённые middleware, логируя неразрешённые.
     *
     * @param array<string> $middleware Массив middleware для фильтрации
     * @return array<string> Массив только разрешённых middleware
     */
    public function sanitizeMiddleware(array $middleware): array
    {
        return array_filter($middleware, function (string $mw) {
            return $this->isMiddlewareAllowed($mw);
        });
    }
}

