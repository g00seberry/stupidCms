<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\RouteNode\Kinds;

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use App\Rules\ReservedPrefixRule;
use App\Rules\RouteConflictRule;
use Illuminate\Validation\Rule;

/**
 * Билдер правил валидации для узлов типа ROUTE.
 *
 * Строит правила валидации для узлов с kind='route'.
 * Маршруты могут иметь: uri, methods, name, domain, middleware, where, defaults, action_meta, action_type.
 * Маршруты НЕ могут иметь: prefix, namespace, children.
 *
 * @package App\Http\Requests\Admin\RouteNode\Kinds
 */
final class RouteNodeValidationBuilder implements RouteNodeKindValidationBuilderInterface
{
    /**
     * Список валидных HTTP методов для маршрутов.
     *
     * @var array<string>
     */
    private const VALID_HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    /**
     * Список валидных HTTP статусов для редиректов.
     *
     * @var array<int>
     */
    private const VALID_REDIRECT_STATUSES = [301, 302, 307, 308];

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
     * - action_type: required, Rule::in([controller, view, redirect])
     * - action_meta: required, array (с условной валидацией в зависимости от action_type)
     *   - Для CONTROLLER: action_meta.action обязателен, string
     *   - Для VIEW: action_meta.view обязателен, string; action_meta.data опционален, array
     *   - Для REDIRECT: action_meta.to обязателен, string; action_meta.status опционален, integer, in:301,302,307,308
     * - Запретить: prefix, namespace, children
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function buildRulesForStore(): array
    {
        $actionTypeValues = RouteNodeActionType::values();
        $httpMethods = self::VALID_HTTP_METHODS;

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
                'action_meta' => ['required', 'array'],
                // для CONTROLLER
                'action_meta.action' => [
                    'required_if:action_type,controller',
                    'prohibited_unless:action_type,controller',
                    'string',
                    'max:512',
                ],
                // для VIEW
                'action_meta.view' => [
                    'required_if:action_type,view',
                    'prohibited_unless:action_type,view',
                    'string',
                    'max:512',
                ],
                'action_meta.data' => [
                    'nullable',
                    'prohibited_unless:action_type,view',
                    'array',
                ],
                // для REDIRECT
                'action_meta.to' => [
                    'required_if:action_type,redirect',
                    'prohibited_unless:action_type,redirect',
                    'string',
                    'max:255',
                ],
                'action_meta.status' => [
                    'nullable',
                    'prohibited_unless:action_type,redirect',
                    'integer',
                    Rule::in(self::VALID_REDIRECT_STATUSES),
                ],
            ],
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
     * - action_type: sometimes, Rule::in([controller, view, redirect])
     * - action_meta: sometimes, nullable, array (с условной валидацией в зависимости от action_type)
     *   - Для CONTROLLER: action_meta.action обязателен, string
     *   - Для VIEW: action_meta.view обязателен, string; action_meta.data опционален, array
     *   - Для REDIRECT: action_meta.to обязателен, string; action_meta.status опционален, integer, in:301,302,307,308
     * - Запретить: prefix, namespace, children
     *
     * @param \App\Models\RouteNode|null $routeNode Текущий RouteNode из route
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function buildRulesForUpdate(?RouteNode $routeNode): array
    {
        $actionTypeValues = RouteNodeActionType::values();
        $httpMethods = self::VALID_HTTP_METHODS;
        
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
                'action_meta' => ['sometimes', 'nullable', 'array'],
                // для CONTROLLER
                'action_meta.action' => [
                    'required_if:action_type,controller',
                    'prohibited_unless:action_type,controller',
                    'string',
                    'max:512',
                ],
                // для VIEW
                'action_meta.view' => [
                    'required_if:action_type,view',
                    'prohibited_unless:action_type,view',
                    'string',
                    'max:512',
                ],
                'action_meta.data' => [
                    'nullable',
                    'prohibited_unless:action_type,view',
                    'array',
                ],
                // для REDIRECT
                'action_meta.to' => [
                    'required_if:action_type,redirect',
                    'prohibited_unless:action_type,redirect',
                    'string',
                    'max:255',
                ],
                'action_meta.status' => [
                    'nullable',
                    'prohibited_unless:action_type,redirect',
                    'integer',
                    Rule::in(self::VALID_REDIRECT_STATUSES),
                ],
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
            'uri.required' => 'Поле uri обязательно для узлов типа route.',
            'uri.string' => 'Поле uri должно быть строкой.',
            'uri.max' => 'Поле uri не может быть длиннее 255 символов.',
            'methods.required' => 'Поле methods обязательно для узлов типа route.',
            'methods.array' => 'Поле methods должно быть массивом.',
            'methods.min' => 'Поле methods должно содержать хотя бы один метод.',
            'methods.*.in' => 'HTTP метод должен быть одним из: ' . implode(', ', self::VALID_HTTP_METHODS) . '.',
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
            'action_meta.required' => 'Поле action_meta обязательно для узлов типа route.',
            'action_meta.array' => 'Поле action_meta должно быть массивом.',
            'action_meta.action.required_if' => 'Поле action_meta.action обязательно для action_type=controller.',
            'action_meta.action.string' => 'Поле action_meta.action должно быть строкой.',
            'action_meta.action.max' => 'Поле action_meta.action не может быть длиннее 512 символов.',
            'action_meta.view.required_if' => 'Поле action_meta.view обязательно для action_type=view.',
            'action_meta.view.string' => 'Поле action_meta.view должно быть строкой.',
            'action_meta.view.max' => 'Поле action_meta.view не может быть длиннее 512 символов.',
            'action_meta.data.array' => 'Поле action_meta.data должно быть массивом.',
            'action_meta.to.required_if' => 'Поле action_meta.to обязательно для action_type=redirect.',
            'action_meta.to.string' => 'Поле action_meta.to должно быть строкой.',
            'action_meta.to.max' => 'Поле action_meta.to не может быть длиннее 255 символов.',
            'action_meta.status.integer' => 'Поле action_meta.status должно быть целым числом.',
            'action_meta.status.in' => 'Поле action_meta.status должно быть одним из: ' . implode(', ', self::VALID_REDIRECT_STATUSES) . '.'
        ];
    }
}

