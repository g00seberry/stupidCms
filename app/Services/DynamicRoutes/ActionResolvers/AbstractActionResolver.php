<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\ActionResolvers;

use App\Models\RouteNode;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use Illuminate\Support\Facades\Log;

/**
 * Абстрактный базовый класс для резолверов действий.
 *
 * Содержит общую логику резолвинга:
 * - Обработка ошибок через try-catch
 * - Логирование ошибок
 * - Обработка fallback (abort(404))
 *
 * Специфичная логика валидации (контроллеры, методы) реализуется
 * в конкретных резолверах (например, ControllerActionResolver).
 *
 * @package App\Services\DynamicRoutes\ActionResolvers
 */
abstract class AbstractActionResolver implements ActionResolverInterface
{
    /**
     * @param \App\Services\DynamicRoutes\DynamicRouteGuard $guard Guard для проверки конфликтов и префиксов (опционально)
     */
    public function __construct(
        protected DynamicRouteGuard $guard,
    ) {}

    /**
     * Разрешить действие для маршрута.
     *
     * Реализация по умолчанию: вызывает doResolve() и обрабатывает ошибки.
     * Дочерние классы должны реализовать doResolve() для специфичной логики.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return callable|string|array<string>|null Действие для маршрута или null при ошибке
     */
    public function resolve(RouteNode $node): callable|string|array|null
    {
        try {
            return $this->doResolve($node);
        } catch (\Throwable $e) {
            Log::error('Dynamic route: ошибка при разрешении действия', [
                'route_node_id' => $node->id,
                'action_type' => $node->action_type?->value,
                'action' => $node->action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->createFallbackAction();
        }
    }


    /**
     * Создать fallback действие (abort(404)).
     *
     * @return callable Closure, который возвращает 404
     */
    protected function createFallbackAction(): callable
    {
        return fn() => abort(404);
    }

    /**
     * Выполнить разрешение действия.
     *
     * Этот метод должен быть реализован в дочерних классах
     * для выполнения специфичной логики разрешения.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return callable|string|array<string>|null Действие для маршрута или null при ошибке
     */
    abstract protected function doResolve(RouteNode $node): callable|string|array|null;
}

