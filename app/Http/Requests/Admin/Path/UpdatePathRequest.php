<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path;

use App\Http\Requests\Admin\Path\Concerns\PathConstraintsValidationRules;
use App\Http\Requests\Admin\Path\Concerns\PathValidationRules;
use App\Models\Path;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для обновления Path.
 *
 * Валидирует данные для обновления поля blueprint:
 * - name: опциональное имя поля (regex: a-z0-9_)
 * - parent_id: опциональный ID родительского поля
 * - data_type: запрещен (нельзя изменять после создания)
 * - cardinality: опциональная кардинальность (one/many)
 * - is_indexed: опциональный флаг индексации
 * - validation_rules: опциональные правила валидации (массив)
 *   - validation_rules.required: опциональный флаг обязательности поля
 *   - validation_rules.min: опциональное минимальное значение (numeric)
 *   - validation_rules.max: опциональное максимальное значение (numeric)
 *   - validation_rules.pattern: опциональный regex паттерн (string)
 *   - validation_rules.distinct: опциональный флаг уникальности элементов массива (boolean)
 *   - validation_rules.required_if, validation_rules.prohibited_unless, validation_rules.required_unless, validation_rules.prohibited_if: опциональные условные правила (расширенный формат: array с полями 'field', 'value', 'operator')
 *   - validation_rules.field_comparison: опциональное правило сравнения полей (array)
 *   - validation_rules.*: разрешены любые дополнительные ключи
 * - constraints: опциональные ограничения для полей (массив)
 *   Формат зависит от текущего data_type поля (data_type нельзя изменять):
 *   - Для data_type='ref': constraints.allowed_post_type_ids (массив ID допустимых типов записей, минимум 1 элемент)
 *   - Для data_type='media': constraints.allowed_mimes (будущая поддержка)
 *   - Для других типов данных: constraints запрещены
 *
 * @package App\Http\Requests\Admin\Path
 */
class UpdatePathRequest extends FormRequest
{
    use PathValidationRules;
    use PathConstraintsValidationRules;

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
     * - name: опциональное имя поля (regex: a-z0-9_)
     * - parent_id: опциональный ID родительского поля (существующий path)
     * - data_type: запрещен (нельзя изменять после создания)
     * - cardinality: опциональная кардинальность (one/many)
     * - is_indexed: опциональный флаг индексации
     * - validation_rules: опциональные правила валидации (массив)
     *   - validation_rules.required: опциональный флаг обязательности поля
     *   - validation_rules.min: опциональное минимальное значение (numeric)
     *   - validation_rules.max: опциональное максимальное значение (numeric)
     *   - validation_rules.pattern: опциональный regex паттерн (string)
     *   - validation_rules.distinct: опциональный флаг уникальности элементов массива (boolean)
     *   - validation_rules.required_if, validation_rules.prohibited_unless, validation_rules.required_unless, validation_rules.prohibited_if: опциональные условные правила (расширенный формат: array с полями 'field', 'value', 'operator')
     *   - validation_rules.unique: опциональное правило уникальности (расширенный формат: array с обязательным полем 'table')
     *   - validation_rules.exists: опциональное правило существования (расширенный формат: array с обязательным полем 'table')
     *   - validation_rules.field_comparison: опциональное правило сравнения полей (array)
     *   - validation_rules.*: разрешены любые дополнительные ключи
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Получаем текущий Path для определения data_type и blueprint_id
        $path = $this->route('path');
        $blueprintId = ($path instanceof Path) ? $path->blueprint_id : 0;

        return array_merge(
            [
                'name' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
                'data_type' => ['prohibited'],
                'parent_id' => [
                    'sometimes',
                    'nullable',
                    'integer',
                    Rule::exists('paths', 'id')->where('blueprint_id', $blueprintId),
                ],
            ],
            $this->getCommonPathRules(),
            $this->getValidationRulesRules(),
            $this->getConstraintsRulesForUpdate($path)
        );
    }

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function messages(): array
    {
        return array_merge(
            $this->getPathValidationMessages(),
            $this->getConstraintsValidationMessages()
        );
    }
}

