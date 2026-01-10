<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\RouteNode\Kinds;

use App\Enums\RouteNodeKind;
use App\Rules\ReservedPrefixRule;
use App\Models\RouteNode;
use Illuminate\Validation\Rule;

/**
 * Билдер правил валидации для узлов типа GROUP.
 *
 * Строит правила валидации для узлов с kind='group'.
 * Группы маршрутов могут иметь: prefix, domain, namespace, middleware, where, children.
 * Группы НЕ могут иметь: uri, methods, name, action, action_type, entry_id.
 *
 * @package App\Http\Requests\Admin\RouteNode\Kinds
 */
final class GroupNodeValidationBuilder implements RouteNodeKindValidationBuilderInterface
{
    /**
     * Получить kind, который поддерживает этот билдер.
     *
     * @return string
     */
    public function getSupportedKind(): string
    {
        return RouteNodeKind::GROUP->value;
    }

    /**
     * Построить правила валидации для group-узлов при создании.
     *
     * Правила для StorePathRequest:
     * - prefix: nullable, string, max:255, ReservedPrefixRule
     * - domain: nullable, string, max:255
     * - namespace: nullable, string, max:255
     * - middleware: nullable, array
     * - where: nullable, array
     * - children: nullable, array (для будущей поддержки)
     * - Запретить: uri, methods, name, action, action_type, entry_id
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function buildRulesForStore(): array
    {
        return array_merge(
            [
                'prefix' => ['nullable', 'string', 'max:255', new ReservedPrefixRule()],
                'domain' => ['nullable', 'string', 'max:255'],
                'namespace' => ['nullable', 'string', 'max:255'],
                'middleware' => ['nullable', 'array'],
                'middleware.*' => ['string'],
                'where' => ['nullable', 'array'],
                'children' => ['nullable', 'array'], // Для будущей поддержки
            ],
        );
    }

    /**
     * Построить правила валидации для group-узлов при обновлении.
     *
     * Правила для UpdateRouteNodeRequest (аналогично Store, но с sometimes):
     * - prefix: sometimes, nullable, string, max:255, ReservedPrefixRule
     * - domain: sometimes, nullable, string, max:255
     * - namespace: sometimes, nullable, string, max:255
     * - middleware: sometimes, nullable, array
     * - where: sometimes, nullable, array
     * - children: sometimes, nullable, array
     * - Запретить: uri, methods, name, action, action_type, entry_id
     *
     * @param \App\Models\RouteNode|null $routeNode Текущий RouteNode из route
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function buildRulesForUpdate(?RouteNode $routeNode): array
    {
        return array_merge(
            [
                'prefix' => ['sometimes', 'nullable', 'string', 'max:255', new ReservedPrefixRule()],
                'domain' => ['sometimes', 'nullable', 'string', 'max:255'],
                'namespace' => ['sometimes', 'nullable', 'string', 'max:255'],
                'middleware' => ['sometimes', 'nullable', 'array'],
                'middleware.*' => ['string'],
                'where' => ['sometimes', 'nullable', 'array'],
                'children' => ['sometimes', 'nullable', 'array'],
            ],
        );
    }

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function buildMessages(): array
    {
        return [
            'prefix.string' => 'Поле prefix должно быть строкой.',
            'prefix.max' => 'Поле prefix не может быть длиннее 255 символов.',
            'domain.string' => 'Поле domain должно быть строкой.',
            'domain.max' => 'Поле domain не может быть длиннее 255 символов.',
            'namespace.string' => 'Поле namespace должно быть строкой.',
            'namespace.max' => 'Поле namespace не может быть длиннее 255 символов.',
            'middleware.array' => 'Поле middleware должно быть массивом.',
            'where.array' => 'Поле where должно быть массивом.',
            'children.array' => 'Поле children должно быть массивом.',
        ];
    }
}

