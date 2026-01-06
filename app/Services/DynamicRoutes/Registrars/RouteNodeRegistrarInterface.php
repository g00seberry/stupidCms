<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Registrars;

use App\Models\RouteNode;

/**
 * Интерфейс для регистраторов узлов маршрутов.
 *
 * Определяет контракт для регистрации RouteNode в Laravel Router.
 * Каждый регистратор отвечает за регистрацию узлов определённого типа (GROUP или ROUTE).
 *
 * @package App\Services\DynamicRoutes\Registrars
 */
interface RouteNodeRegistrarInterface
{
    /**
     * Зарегистрировать узел маршрута.
     *
     * Регистрирует узел в Laravel Router в соответствии с его типом и настройками.
     * Выполняет валидацию, построение атрибутов и регистрацию маршрута/группы.
     *
     * @param \App\Models\RouteNode $node Узел для регистрации
     * @return void
     */
    public function register(RouteNode $node): void;
}

