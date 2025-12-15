<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RouteNodeActionType;
use App\Enums\RouteNodeKind;
use App\Rules\ControllerActionFormatRule;
use App\Rules\ReservedPrefixRule;
use App\Rules\RouteConflictRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для создания узла маршрута (RouteNode).
 *
 * Валидирует данные для создания узла маршрута:
 * - Обязательные: kind, action_type
 * - Опциональные: все остальные поля
 * - Кастомные правила: проверка запрещённых префиксов, формата action
 *
 * @package App\Http\Requests\Admin
 */
class StoreRouteNodeRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Авторизация обрабатывается middleware маршрута.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - kind: обязательный enum (group, route)
     * - action_type: обязательный enum (controller, entry)
     * - parent_id: опциональный ID родителя (должен существовать)
     * - sort_order: опциональный порядок сортировки (>= 0)
     * - enabled: опциональный boolean
     * - name: опциональное имя маршрута
     * - domain: опциональный домен
     * - prefix: опциональный префикс (не должен быть зарезервирован)
     * - namespace: опциональный namespace контроллеров
     * - methods: опциональный массив HTTP методов (для kind=route)
     * - uri: опциональный URI паттерн (не должен быть зарезервирован, для kind=route)
     * - action: опциональное действие (для action_type=controller, проверка формата)
     * - entry_id: опциональный ID Entry (для action_type=entry, должен существовать)
     * - middleware: опциональный массив middleware
     * - where: опциональный массив ограничений параметров
     * - defaults: опциональный массив значений по умолчанию
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kindValues = RouteNodeKind::values();
        $actionTypeValues = RouteNodeActionType::values();
        $httpMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

        return [
            'kind' => ['required', Rule::in($kindValues)],
            'parent_id' => ['nullable', 'integer', 'exists:route_nodes,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'enabled' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'prefix' => ['nullable', 'string', 'max:255', new ReservedPrefixRule()],
            'namespace' => ['nullable', 'string', 'max:255'],
            'methods' => ['nullable', 'array'],
            'methods.*' => [Rule::in($httpMethods)],
            'uri' => [
                'nullable',
                'string',
                'max:255',
                new ReservedPrefixRule(),
                new RouteConflictRule(),
            ],
            'action_type' => ['required', Rule::in($actionTypeValues)],
            'action' => [
                'nullable',
                'string',
                'max:255',
                Rule::prohibitedIf(fn () => $this->input('action_type') === 'entry'),
                new ControllerActionFormatRule(),
            ],
            'entry_id' => [
                'nullable',
                'integer',
                'exists:entries,id',
                Rule::requiredIf(fn () => $this->input('action_type') === 'entry'),
            ],
            'middleware' => ['nullable', 'array'],
            'middleware.*' => ['string'],
            'where' => ['nullable', 'array'],
            'defaults' => ['nullable', 'array'],
            'readonly' => ['prohibited'], // Запрещаем создание readonly маршрутов через API (только декларативные могут быть readonly)
        ];
    }

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function messages(): array
    {
        return [
            'kind.required' => 'Поле kind обязательно для заполнения.',
            'kind.in' => 'Поле kind должно быть одним из: ' . implode(', ', RouteNodeKind::values()) . '.',
            'action_type.required' => 'Поле action_type обязательно для заполнения.',
            'action_type.in' => 'Поле action_type должно быть одним из: ' . implode(', ', RouteNodeActionType::values()) . '.',
            'parent_id.exists' => 'Указанный родительский узел не существует.',
            'entry_id.exists' => 'Указанная Entry не существует.',
            'methods.*.in' => 'HTTP метод должен быть одним из: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD.',
        ];
    }
}

