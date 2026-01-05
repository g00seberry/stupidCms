<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Builders;

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use Illuminate\Support\Facades\Log;

/**
 * Билдер для создания узлов типа ROUTE.
 *
 * Отвечает за создание и настройку конкретных маршрутов:
 * - Установка полей маршрута (uri, methods, name, domain, middleware, where, defaults)
 * - Обработка action_type и action
 *
 * @package App\Services\DynamicRoutes\Builders
 */
class RouteRouteNodeBuilder extends AbstractRouteNodeBuilder
{
    /**
     * Проверить, поддерживает ли билдер указанный тип узла.
     *
     * @param \App\Enums\RouteNodeKind $kind Тип узла
     * @return bool true если kind === ROUTE, false иначе
     */
    public function supports(RouteNodeKind $kind): bool
    {
        return $kind === RouteNodeKind::ROUTE;
    }

    /**
     * Построить специфичные поля узла типа ROUTE.
     *
     * Устанавливает поля, специфичные для маршрутов:
     * - uri
     * - methods
     * - name
     * - domain
     * - middleware
     * - where
     * - defaults
     * - action_type и action
     *
     * @param \App\Models\RouteNode $node Узел для настройки
     * @param array<string, mixed> $data Данные конфигурации
     * @param string $source Источник маршрута (для логирования)
     * @return void
     */
    protected function buildSpecificFields(RouteNode $node, array $data, string $source): void
    {
        // Устанавливаем поля маршрута
        $this->buildRouteFields($node, $data);

        // Обрабатываем action_type и action
        $this->buildAction($node, $data);
    }

    /**
     * Установить поля маршрута.
     *
     * Устанавливает поля, специфичные для маршрутов:
     * - uri: URI паттерн маршрута
     * - methods: HTTP методы (массив)
     * - name: имя маршрута
     * - domain: домен для маршрута
     * - middleware: массив middleware
     * - where: ограничения параметров маршрута
     * - defaults: значения по умолчанию для параметров
     *
     * @param \App\Models\RouteNode $node Узел для настройки
     * @param array<string, mixed> $data Данные конфигурации
     * @return void
     */
    protected function buildRouteFields(RouteNode $node, array $data): void
    {
        $node->uri = $data['uri'] ?? null;
        $node->methods = $data['methods'] ?? null;
        $node->name = $data['name'] ?? null;
        $node->domain = $data['domain'] ?? null;
        $node->middleware = $data['middleware'] ?? null;
        $node->where = $data['where'] ?? null;
        $node->defaults = $data['defaults'] ?? null;
    }

    /**
     * Построить action для маршрута.
     *
     * Обрабатывает action_type и action:
     * - Нормализует action_type в enum
     * - Устанавливает action и entry_id в зависимости от типа
     * - По умолчанию использует CONTROLLER, если action_type не указан
     *
     * @param \App\Models\RouteNode $node Узел для настройки
     * @param array<string, mixed> $data Данные конфигурации
     * @return void
     */
    protected function buildAction(RouteNode $node, array $data): void
    {
        if (isset($data['action_type'])) {
            $actionType = $this->normalizeActionType($data['action_type']);
            if ($actionType !== null) {
                $node->action_type = $actionType;
                $node->action = $data['action'] ?? null;
                $node->entry_id = $data['entry_id'] ?? null;
            } else {
                // Если action_type указан, но невалиден, логируем и используем CONTROLLER по умолчанию
                Log::warning('Declarative route: invalid action_type, using CONTROLLER', [
                    'action_type' => $data['action_type'],
                    'node_id' => $node->id,
                ]);
                $node->action_type = RouteNodeActionType::CONTROLLER;
                $node->action = $data['action'] ?? null;
            }
        } else {
            // По умолчанию CONTROLLER
            $node->action_type = RouteNodeActionType::CONTROLLER;
            $node->action = $data['action'] ?? null;
        }
    }

    /**
     * Нормализовать action_type в enum RouteNodeActionType.
     *
     * Преобразует строковое значение или enum в RouteNodeActionType.
     *
     * @param mixed $actionType Значение action_type (строка или RouteNodeActionType)
     * @return \App\Enums\RouteNodeActionType|null Нормализованный enum или null при ошибке
     */
    protected function normalizeActionType(mixed $actionType): ?RouteNodeActionType
    {
        if ($actionType instanceof RouteNodeActionType) {
            return $actionType;
        }

        if (is_string($actionType)) {
            try {
                return RouteNodeActionType::from($actionType);
            } catch (\ValueError $e) {
                Log::warning('Declarative route: invalid action_type', [
                    'action_type' => $actionType,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        }

        Log::warning('Declarative route: action_type must be string or RouteNodeActionType', [
            'action_type' => $actionType,
            'type' => gettype($actionType),
        ]);

        return null;
    }
}

