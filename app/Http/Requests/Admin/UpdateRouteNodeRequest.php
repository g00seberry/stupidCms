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
 * Request для обновления узла маршрута (RouteNode).
 *
 * Валидирует данные для обновления узла маршрута:
 * - Все поля опциональны (sometimes)
 * - Кастомные правила: проверка запрещённых префиксов, формата action
 *
 * @package App\Http\Requests\Admin
 */
class UpdateRouteNodeRequest extends FormRequest
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
     * Валидирует (все поля опциональны):
     * - kind: enum (group, route)
     * - action_type: enum (controller, entry)
     * - parent_id: ID родителя (должен существовать)
     * - sort_order: порядок сортировки (>= 0)
     * - enabled: boolean
     * - name: имя маршрута
     * - domain: домен
     * - prefix: префикс (не должен быть зарезервирован)
     * - namespace: namespace контроллеров
     * - methods: массив HTTP методов
     * - uri: URI паттерн (не должен быть зарезервирован)
     * - action: действие (проверка формата для controller)
     * - entry_id: ID Entry (должен существовать)
     * - middleware: массив middleware
     * - where: массив ограничений параметров
     * - defaults: массив значений по умолчанию
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $kindValues = RouteNodeKind::values();
        $actionTypeValues = RouteNodeActionType::values();
        $httpMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        
        // Получаем ID маршрута из route параметра для исключения из проверки конфликтов
        $routeId = (int) $this->route('id');

        return [
            'kind' => ['sometimes', Rule::in($kindValues)],
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
                new RouteConflictRule($routeId),
            ],
            'action_type' => ['sometimes', Rule::in($actionTypeValues)],
            'action' => ['nullable', 'string', 'max:255', new ControllerActionFormatRule()],
            'entry_id' => ['nullable', 'integer', 'exists:entries,id'],
            'middleware' => ['nullable', 'array'],
            'middleware.*' => ['string'],
            'where' => ['nullable', 'array'],
            'defaults' => ['nullable', 'array'],
            'readonly' => ['prohibited'], // Поле readonly нельзя изменять через API
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
            'kind.in' => 'Поле kind должно быть одним из: ' . implode(', ', RouteNodeKind::values()) . '.',
            'action_type.in' => 'Поле action_type должно быть одним из: ' . implode(', ', RouteNodeActionType::values()) . '.',
            'parent_id.exists' => 'Указанный родительский узел не существует.',
            'entry_id.exists' => 'Указанная Entry не существует.',
            'methods.*.in' => 'HTTP метод должен быть одним из: GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD.',
        ];
    }
}

