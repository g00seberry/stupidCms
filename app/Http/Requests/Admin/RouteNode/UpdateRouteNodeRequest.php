<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\RouteNode;

use App\Enums\RouteNodeKind;
use App\Http\Requests\Admin\RouteNode\Concerns\RouteNodeKindValidationRules;
use App\Http\Requests\Admin\RouteNode\Concerns\RouteNodeValidationRules;
use App\Models\RouteNode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для обновления узла маршрута (RouteNode).
 *
 * Валидирует данные для обновления узла маршрута:
 * - Все поля опциональны (sometimes)
 * - Общие правила: kind, parent_id, sort_order, enabled, readonly
 * - Правила по kind: динамически через систему билдеров на основе текущего kind из модели
 *   - Для kind=group: prefix, domain, namespace, middleware, where, children
 *   - Для kind=route: uri, methods, name, domain, middleware, where, defaults, action, action_type, entry_id
 *
 * @package App\Http\Requests\Admin\RouteNode
 */
class UpdateRouteNodeRequest extends FormRequest
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
     * - Специфичные правила для текущего kind из модели RouteNode (через RouteNodeKindValidationRules)
     *
     * Для обновления все поля опциональны (sometimes), включая kind.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Получаем RouteNode по ID из route для определения текущего kind
        $id = (int) $this->route('id');
        $routeNode = $id > 0 ? RouteNode::find($id) : null;

        // Получаем общие правила и изменяем kind на sometimes для обновления
        $commonRules = $this->getCommonRouteNodeRules();
        $kindValues = RouteNodeKind::values();
        $commonRules['kind'] = ['sometimes', Rule::in($kindValues)];

        return array_merge(
            $commonRules,
            $this->getKindRulesForUpdate($routeNode)
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

