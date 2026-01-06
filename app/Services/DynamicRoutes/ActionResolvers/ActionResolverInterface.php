<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\ActionResolvers;

use App\Models\RouteNode;

/**
 * Интерфейс для резолверов действий маршрутов.
 *
 * Определяет контракт для разрешения действий (action) из RouteNode
 * в формат, понятный Laravel Router (callable, string, array).
 * Каждый резолвер отвечает за обработку определённого типа действия.
 *
 * @package App\Services\DynamicRoutes\ActionResolvers
 */
interface ActionResolverInterface
{
    /**
     * Разрешить действие для маршрута.
     *
     * Преобразует action из RouteNode в формат для Laravel Router:
     * - callable: closure или invokable объект
     * - string: имя invokable контроллера
     * - array: [Controller::class, 'method']
     * - null: действие не может быть разрешено (ошибка)
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return callable|string|array<string>|null Действие для маршрута или null при ошибке
     */
    public function resolve(RouteNode $node): callable|string|array|null;

    /**
     * Проверить, поддерживает ли резолвер указанный узел.
     *
     * Определяет, может ли резолвер обработать action_type и action данного узла.
     *
     * @param \App\Models\RouteNode $node Узел маршрута
     * @return bool true если резолвер поддерживает узел, false иначе
     */
    public function supports(RouteNode $node): bool;
}

