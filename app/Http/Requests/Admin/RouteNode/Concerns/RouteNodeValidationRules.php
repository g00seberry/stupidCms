<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\RouteNode\Concerns;

use App\Enums\RouteNodeKind;
use Illuminate\Validation\Rule;

/**
 * Трейт с общими правилами валидации для RouteNode.
 *
 * Содержит правила валидации, общие для StoreRouteNodeRequest и UpdateRouteNodeRequest.
 * Эти правила применяются ко всем узлам независимо от kind.
 *
 * @package App\Http\Requests\Admin\RouteNode\Concerns
 */
trait RouteNodeValidationRules
{
    /**
     * Получить общие правила валидации для RouteNode.
     *
     * Возвращает правила валидации для полей, общих для всех kind:
     * - kind: обязательный enum (group, route)
     * - parent_id: опциональный ID родителя (должен существовать)
     * - sort_order: опциональный порядок сортировки (>= 0)
     * - enabled: опциональный boolean
     * - readonly: запрещён (только декларативные могут быть readonly)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function getCommonRouteNodeRules(): array
    {
        $kindValues = RouteNodeKind::values();

        return [
            'kind' => ['required', Rule::in($kindValues)],
            'parent_id' => ['nullable', 'integer', 'exists:route_nodes,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'enabled' => ['nullable', 'boolean'],
            'readonly' => ['prohibited'], // Запрещаем создание readonly маршрутов через API
        ];
    }

    /**
     * Получить кастомные сообщения для ошибок валидации общих полей.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    protected function getRouteNodeValidationMessages(): array
    {
        return [
            'kind.required' => 'Поле kind обязательно для заполнения.',
            'kind.in' => 'Поле kind должно быть одним из: ' . implode(', ', RouteNodeKind::values()) . '.',
            'parent_id.integer' => 'Поле parent_id должно быть целым числом.',
            'parent_id.exists' => 'Указанный родительский узел не существует.',
            'sort_order.integer' => 'Поле sort_order должно быть целым числом.',
            'sort_order.min' => 'Поле sort_order должно быть не меньше 0.',
            'enabled.boolean' => 'Поле enabled должно быть логическим значением.',
            'readonly.prohibited' => 'Поле readonly нельзя устанавливать через API. Только декларативные маршруты могут быть readonly.',
        ];
    }
}

