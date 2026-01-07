<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\RouteNode\Kinds;

use App\Models\RouteNode;

/**
 * Интерфейс для билдеров правил валидации RouteNode по kind.
 *
 * Определяет контракт для построения правил валидации
 * для различных типов узлов маршрутов (group, route).
 *
 * @package App\Http\Requests\Admin\RouteNode\Kinds
 */
interface RouteNodeKindValidationBuilderInterface
{
    /**
     * Получить kind, который поддерживает этот билдер.
     *
     * @return string Значение RouteNodeKind (например, 'group', 'route')
     */
    public function getSupportedKind(): string;

    /**
     * Построить правила валидации для создания RouteNode (StoreRouteNodeRequest).
     *
     * Правила должны быть специфичны для поддерживаемого kind.
     * Общие правила (kind, parent_id, sort_order, enabled, readonly) обрабатываются отдельно.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function buildRulesForStore(): array;

    /**
     * Построить правила валидации для обновления RouteNode (UpdateRouteNodeRequest).
     *
     * Правила должны быть специфичны для поддерживаемого kind.
     * Общие правила обрабатываются отдельно.
     *
     * @param \App\Models\RouteNode|null $routeNode Текущий RouteNode из route
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function buildRulesForUpdate(?RouteNode $routeNode): array;

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function buildMessages(): array;
}

