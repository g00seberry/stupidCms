<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\RouteNode\Kinds;

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use App\Rules\ControllerActionFormatRule;
use App\Rules\ReservedPrefixRule;
use App\Rules\RouteConflictRule;
use Illuminate\Validation\Rule;

/**
 * Билдер правил валидации для узлов типа ROUTE.
 *
 * Строит правила валидации для узлов с kind='route'.
 * Маршруты могут иметь: uri, methods, name, domain, middleware, where, defaults, action, action_type, entry_id.
 * Маршруты НЕ могут иметь: prefix, namespace, children.
 *
 * @package App\Http\Requests\Admin\RouteNode\Kinds
 */
final class RouteKindValidationBuilder implements RouteNodeKindValidationBuilderInterface
{
    /**
     * Получить kind, который поддерживает этот билдер.
     *
     * @return string
     */
    public function getSupportedKind(): string
    {
        return RouteNodeKind::ROUTE->value;
    }

    /**
     * Получить список валидных HTTP методов.
     *
     * @return array<string>
     */
    private function getValidHttpMethods(): array
    {
        return ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
    }

    /**
     * Построить правила валидации для route-узлов при создании.
     *
     * Правила для StoreRouteNodeRequest:
     * - uri: required, string, max:255, ReservedPrefixRule, RouteConflictRule
     * - methods: required, array, min:1
     * - methods.*: Rule::in([GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD])
     * - name: nullable, string, max:255
     * - domain: nullable, string, max:255
     * - middleware: nullable, array
     * - middleware.*: string
     * - where: nullable, array
     * - defaults: nullable, array
     * - action_type: required, Rule::in([controller, entry])
     * - action: nullable (с условиями), string, max:255, ControllerActionFormatRule
     *   - prohibitedIf(action_type === 'entry')
     * - entry_id: nullable (с условиями), integer, exists:entries,id
     *   - requiredIf(action_type === 'entry')
     * - Запретить: prefix, namespace, children
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function buildRulesForStore(): array
    {
        $actionTypeValues = RouteNodeActionType::values();
        $httpMethods = $this->getValidHttpMethods();

        return array_merge(
            [
                'uri' => [
                    'required',
                    'string',
                    'max:255',
                    new ReservedPrefixRule(),
                    new RouteConflictRule(),
                ],
                'methods' => ['required', 'array', 'min:1'],
                'methods.*' => [Rule::in($httpMethods)],
                'name' => ['nullable', 'string', 'max:255'],
                'domain' => ['nullable', 'string', 'max:255'],
                'middleware' => ['nullable', 'array'],
                'middleware.*' => ['string'],
                'where' => ['nullable', 'array'],
                'defaults' => ['nullable', 'array'],
                'action_type' => ['required', Rule::in($actionTypeValues)],
                'action' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::prohibitedIf(fn () => request()->input('action_type') === 'entry'),
                    new ControllerActionFormatRule(),
                ],
                'entry_id' => [
                    'nullable',
                    'integer',
                    'exists:entries,id',
                    Rule::requiredIf(fn () => request()->input('action_type') === 'entry'),
                ],
            ],
            // Запрещаем поля, специфичные для group
            [
                'prefix' => ['prohibited'],
                'namespace' => ['prohibited'],
                'children' => ['prohibited'],
            ]
        );
    }

    /**
     * Построить правила валидации для route-узлов при обновлении.
     *
     * Правила для UpdateRouteNodeRequest (аналогично Store, но с sometimes и nullable):
     * - uri: sometimes, nullable, string, max:255, ReservedPrefixRule, RouteConflictRule
     * - methods: sometimes, nullable, array, min:1
     * - methods.*: Rule::in([GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD])
     * - name: sometimes, nullable, string, max:255
     * - domain: sometimes, nullable, string, max:255
     * - middleware: sometimes, nullable, array
     * - middleware.*: string
     * - where: sometimes, nullable, array
     * - defaults: sometimes, nullable, array
     * - action_type: sometimes, Rule::in([controller, entry])
     * - action: sometimes, nullable, string, max:255, ControllerActionFormatRule
     * - entry_id: sometimes, nullable, integer, exists:entries,id
     * - Запретить: prefix, namespace, children
     *
     * @param \App\Models\RouteNode|null $routeNode Текущий RouteNode из route
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function buildRulesForUpdate(?RouteNode $routeNode): array
    {
        $actionTypeValues = RouteNodeActionType::values();
        $httpMethods = $this->getValidHttpMethods();
        
        // Получаем ID маршрута для исключения из проверки конфликтов
        $routeId = ($routeNode instanceof RouteNode) ? $routeNode->id : null;

        return array_merge(
            [
                'uri' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:255',
                    new ReservedPrefixRule(),
                    new RouteConflictRule($routeId),
                ],
                'methods' => ['sometimes', 'nullable', 'array', 'min:1'],
                'methods.*' => [Rule::in($httpMethods)],
                'name' => ['sometimes', 'nullable', 'string', 'max:255'],
                'domain' => ['sometimes', 'nullable', 'string', 'max:255'],
                'middleware' => ['sometimes', 'nullable', 'array'],
                'middleware.*' => ['string'],
                'where' => ['sometimes', 'nullable', 'array'],
                'defaults' => ['sometimes', 'nullable', 'array'],
                'action_type' => ['sometimes', Rule::in($actionTypeValues)],
                'action' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:255',
                    new ControllerActionFormatRule(),
                ],
                'entry_id' => [
                    'sometimes',
                    'nullable',
                    'integer',
                    'exists:entries,id',
                ],
            ],
            // Запрещаем поля, специфичные для group
            [
                'prefix' => ['prohibited'],
                'namespace' => ['prohibited'],
                'children' => ['prohibited'],
            ]
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
            'uri.required' => 'Поле uri обязательно для узлов типа route.',
            'uri.string' => 'Поле uri должно быть строкой.',
            'uri.max' => 'Поле uri не может быть длиннее 255 символов.',
            'methods.required' => 'Поле methods обязательно для узлов типа route.',
            'methods.array' => 'Поле methods должно быть массивом.',
            'methods.min' => 'Поле methods должно содержать хотя бы один метод.',
            'methods.*.in' => 'HTTP метод должен быть одним из: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD.',
            'name.string' => 'Поле name должно быть строкой.',
            'name.max' => 'Поле name не может быть длиннее 255 символов.',
            'domain.string' => 'Поле domain должно быть строкой.',
            'domain.max' => 'Поле domain не может быть длиннее 255 символов.',
            'middleware.array' => 'Поле middleware должно быть массивом.',
            'middleware.*.string' => 'Все элементы в middleware должны быть строками.',
            'where.array' => 'Поле where должно быть массивом.',
            'defaults.array' => 'Поле defaults должно быть массивом.',
            'action_type.required' => 'Поле action_type обязательно для узлов типа route.',
            'action_type.in' => 'Поле action_type должно быть одним из: ' . implode(', ', RouteNodeActionType::values()) . '.',
            'action.string' => 'Поле action должно быть строкой.',
            'action.max' => 'Поле action не может быть длиннее 255 символов.',
            'entry_id.integer' => 'Поле entry_id должно быть целым числом.',
            'entry_id.exists' => 'Указанная Entry не существует.',
            'prefix.prohibited' => 'Поле prefix не может быть использовано для узлов типа route.',
            'namespace.prohibited' => 'Поле namespace не может быть использовано для узлов типа route.',
            'children.prohibited' => 'Поле children не может быть использовано для узлов типа route.',
        ];
    }
}

