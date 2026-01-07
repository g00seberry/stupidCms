<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\RouteNode\Concerns;

use App\Http\Requests\Admin\RouteNode\Kinds\RouteNodeKindValidationBuilderRegistry;
use App\Models\RouteNode;

/**
 * Трейт с правилами валидации для RouteNode по kind.
 *
 * Содержит методы для сборки правил валидации для различных kind
 * через систему билдеров и Registry.
 *
 * @package App\Http\Requests\Admin\RouteNode\Concerns
 */
trait RouteNodeKindValidationRules
{
    /**
     * Получить регистр билдеров валидации kind.
     *
     * @return \App\Http\Requests\Admin\RouteNode\Kinds\RouteNodeKindValidationBuilderRegistry
     */
    protected function getKindRegistry(): RouteNodeKindValidationBuilderRegistry
    {
        return app(RouteNodeKindValidationBuilderRegistry::class);
    }

    /**
     * Получить правила валидации для kind при создании RouteNode (StoreRouteNodeRequest).
     *
     * Правила строятся динамически на основе kind из запроса:
     * - Получает kind из запроса
     * - Находит соответствующий билдер через Registry
     * - Если билдер найден - использует его правила
     * - Если билдер не найден - запрещает все специфичные поля для данного kind
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function getKindRulesForStore(): array
    {
        $kind = $this->input('kind');

        if (!is_string($kind)) {
            // Если kind не передан, запрещаем все специфичные поля
            return $this->buildProhibitedRulesForUnknownKind();
        }

        $registry = $this->getKindRegistry();
        $builder = $registry->getBuilder($kind);

        if ($builder === null) {
            // Если билдер не найден для данного kind, запрещаем все специфичные поля
            return $this->buildProhibitedRulesForUnknownKind();
        }

        return $builder->buildRulesForStore();
    }

    /**
     * Получить правила валидации для kind при обновлении RouteNode (UpdateRouteNodeRequest).
     *
     * Правила строятся динамически на основе текущего kind из модели RouteNode:
     * - Получает текущий kind из модели RouteNode (kind нельзя изменять)
     * - Находит соответствующий билдер через Registry
     * - Если билдер найден - использует его правила
     * - Если билдер не найден - запрещает все специфичные поля для данного kind
     *
     * @param \App\Models\RouteNode|null $routeNode Текущий RouteNode из route
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function getKindRulesForUpdate(?RouteNode $routeNode): array
    {
        $currentKind = ($routeNode instanceof RouteNode) ? $routeNode->kind?->value : null;

        if ($currentKind === null || !is_string($currentKind)) {
            // Если kind не определён, запрещаем все специфичные поля
            return $this->buildProhibitedRulesForUnknownKind();
        }

        $registry = $this->getKindRegistry();
        $builder = $registry->getBuilder($currentKind);

        if ($builder === null) {
            // Если билдер не найден для данного kind, запрещаем все специфичные поля
            return $this->buildProhibitedRulesForUnknownKind();
        }

        return $builder->buildRulesForUpdate($routeNode);
    }

    /**
     * Получить кастомные сообщения для ошибок валидации kind.
     *
     * Собирает сообщения от всех зарегистрированных билдеров.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    protected function getKindValidationMessages(): array
    {
        $registry = $this->getKindRegistry();
        $messages = [];

        // Собираем сообщения от всех зарегистрированных билдеров
        foreach ($registry->getAllBuilders() as $builder) {
            $builderMessages = $builder->buildMessages();
            $messages = array_merge($messages, $builderMessages);
        }

        return $messages;
    }

    /**
     * Построить правила, запрещающие все специфичные поля для неизвестного kind.
     *
     * Используется когда kind не определён или не поддерживается.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    private function buildProhibitedRulesForUnknownKind(): array
    {
        return [
            'uri' => ['prohibited'],
            'methods' => ['prohibited'],
            'name' => ['prohibited'],
            'prefix' => ['prohibited'],
            'namespace' => ['prohibited'],
            'domain' => ['prohibited'],
            'middleware' => ['prohibited'],
            'where' => ['prohibited'],
            'defaults' => ['prohibited'],
            'action' => ['prohibited'],
            'action_type' => ['prohibited'],
            'entry_id' => ['prohibited'],
            'children' => ['prohibited'],
        ];
    }
}

