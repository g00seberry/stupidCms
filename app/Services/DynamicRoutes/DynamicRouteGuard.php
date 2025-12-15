<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes;

use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use App\Repositories\RouteNodeRepository;
use Illuminate\Database\Eloquent\Collection;
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
     * @param \App\Repositories\RouteNodeRepository|null $repository Репозиторий для загрузки маршрутов из БД
     * @param \App\Services\DynamicRoutes\DeclarativeRouteLoader|null $declarativeLoader Загрузчик декларативных маршрутов
     */
    public function __construct(
        private ?RouteNodeRepository $repository = null,
        private ?DeclarativeRouteLoader $declarativeLoader = null,
    ) {}
    /**
     * Проверить, разрешён ли middleware.
     *
     * Поддерживает параметризованные middleware через паттерны:
     * - 'can:*' разрешает все middleware вида can:action,Model
     * - 'throttle:*' разрешает все middleware вида throttle:60,1
     * - 'App\Http\Middleware\*' разрешает все middleware из этого namespace
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

        // Проверка параметризованных middleware и wildcard паттернов
        foreach ($allowed as $pattern) {
            // Параметризованные middleware (can:*, throttle:*)
            if (str_ends_with($pattern, ':*')) {
                $prefix = substr($pattern, 0, -2); // Убираем ':*'
                if (str_starts_with($middleware, $prefix . ':')) {
                    return true;
                }
            }
            
            // Wildcard для классов middleware (App\Http\Middleware\*)
            if (str_ends_with($pattern, '*')) {
                $prefix = substr($pattern, 0, -1); // Убираем '*'
                if (str_starts_with($middleware, $prefix)) {
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
        $filtered = array_filter($middleware, function (string $mw) {
            return $this->isMiddlewareAllowed($mw);
        });
        
        // Переиндексируем массив, чтобы избежать пропусков в ключах
        // Это предотвращает превращение массива в объект при JSON сериализации
        return array_values($filtered);
    }

    /**
     * Проверить конфликт маршрута с существующими маршрутами.
     *
     * Проверяет, существует ли уже маршрут с таким же URI и методами
     * в декларативных маршрутах или в БД.
     *
     * @param string $uri URI маршрута
     * @param array<string> $methods HTTP методы
     * @param int|null $excludeId ID маршрута для исключения из проверки (при обновлении)
     * @return \App\Models\RouteNode|null Конфликтующий маршрут или null, если конфликта нет
     */
    public function checkConflict(string $uri, array $methods, ?int $excludeId = null): ?RouteNode
    {
        // Нормализуем URI (убираем ведущий слэш для сравнения)
        $normalizedUri = ltrim($uri, '/');

        // Проверяем декларативные маршруты (они включены в общее дерево)
        // Для проверки конфликтов используем только декларативные, чтобы не проверять дважды
        if ($this->declarativeLoader) {
            $declarativeNodes = $this->declarativeLoader->loadAll();
            $conflict = $this->findConflictInCollection($declarativeNodes, $normalizedUri, $methods, null);
            if ($conflict) {
                return $conflict;
            }
        }

        // Проверяем маршруты из БД
        if ($this->repository) {
            $dbNodes = $this->repository->getEnabledTree();
            $conflict = $this->findConflictInCollection($dbNodes, $normalizedUri, $methods, $excludeId);
            if ($conflict) {
                return $conflict;
            }
        }

        return null;
    }

    /**
     * Найти конфликт в коллекции маршрутов.
     *
     * Рекурсивно проверяет все маршруты в коллекции (включая вложенные в группы).
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, \App\Models\RouteNode> $nodes Коллекция узлов
     * @param string $normalizedUri Нормализованный URI (без ведущего слэша)
     * @param array<string> $methods HTTP методы
     * @param int|null $excludeId ID маршрута для исключения
     * @return \App\Models\RouteNode|null Конфликтующий маршрут или null
     */
    private function findConflictInCollection(
        Collection $nodes,
        string $normalizedUri,
        array $methods,
        ?int $excludeId
    ): ?RouteNode {
        foreach ($nodes as $node) {
            // Пропускаем исключённый маршрут
            if ($excludeId !== null && $node->id === $excludeId) {
                continue;
            }

            if ($node->kind === RouteNodeKind::GROUP) {
                // Для группы проверяем детей рекурсивно
                if ($node->relationLoaded('children') && $node->children) {
                    $conflict = $this->findConflictInCollection(
                        $node->children,
                        $normalizedUri,
                        $methods,
                        $excludeId
                    );
                    if ($conflict) {
                        return $conflict;
                    }
                }
            } elseif ($node->kind === RouteNodeKind::ROUTE) {
                // Для маршрута проверяем URI и методы
                if ($node->uri && $node->methods) {
                    $nodeUri = ltrim($node->uri, '/');
                    
                    // Проверяем совпадение URI
                    if ($nodeUri === $normalizedUri) {
                        // Проверяем пересечение методов
                        $intersection = array_intersect(
                            array_map('strtoupper', $node->methods),
                            array_map('strtoupper', $methods)
                        );
                        
                        if (!empty($intersection)) {
                            return $node;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Проверить, можно ли создать маршрут с указанным URI и методами.
     *
     * Проверяет конфликты и зарезервированные префиксы.
     *
     * @param string $uri URI маршрута
     * @param array<string> $methods HTTP методы
     * @param int|null $excludeId ID маршрута для исключения (при обновлении)
     * @return array{allowed: bool, reason: string|null, conflicting_route: \App\Models\RouteNode|null}
     */
    public function canCreateRoute(string $uri, array $methods, ?int $excludeId = null): array
    {
        // Проверяем зарезервированные префиксы
        $normalizedUri = ltrim($uri, '/');
        $firstSegment = explode('/', $normalizedUri)[0] ?? '';
        
        if ($this->isPrefixReserved($firstSegment)) {
            return [
                'allowed' => false,
                'reason' => "Префикс '{$firstSegment}' зарезервирован для системных маршрутов",
                'conflicting_route' => null,
            ];
        }

        // Проверяем конфликты
        $conflict = $this->checkConflict($uri, $methods, $excludeId);
        if ($conflict) {
            // Определяем источник по ID: отрицательные ID = декларативные маршруты
            $isDeclarative = $conflict->id < 0;
            $sourceLabel = $isDeclarative ? 'декларативный файл' : 'БД';
            
            return [
                'allowed' => false,
                'reason' => "Маршрут с URI '{$uri}' и методами [" . implode(', ', $methods) . "] уже существует в {$sourceLabel}",
                'conflicting_route' => $conflict,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'conflicting_route' => null,
        ];
    }
}

