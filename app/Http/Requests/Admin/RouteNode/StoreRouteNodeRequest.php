<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\RouteNode;

use App\Http\Requests\Admin\RouteNode\Concerns\RouteNodeKindValidationRules;
use App\Http\Requests\Admin\RouteNode\Concerns\RouteNodeValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для создания узла маршрута (RouteNode).
 *
 * Валидирует данные для создания узла маршрута:
 * - Общие правила: kind, parent_id, sort_order, enabled, readonly
 * - Правила по kind: динамически через систему билдеров
 *   - Для kind=group: prefix, domain, namespace, middleware, where, children
 *   - Для kind=route: uri, methods, name, domain, middleware, where, defaults, action, action_type, entry_id
 *
 * @package App\Http\Requests\Admin\RouteNode
 */
class StoreRouteNodeRequest extends FormRequest
{
    use RouteNodeValidationRules;
    use RouteNodeKindValidationRules;

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
     * Правила формируются динамически:
     * - Общие правила для всех kind (через RouteNodeValidationRules)
     * - Специфичные правила для конкретного kind (через RouteNodeKindValidationRules)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(
            $this->getCommonRouteNodeRules(),
            $this->getKindRulesForStore()
        );
    }

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * Сообщения собираются из:
     * - Общих правил (RouteNodeValidationRules)
     * - Правил по kind (RouteNodeKindValidationRules)
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function messages(): array
    {
        return array_merge(
            $this->getRouteNodeValidationMessages(),
            $this->getKindValidationMessages()
        );
    }
}

