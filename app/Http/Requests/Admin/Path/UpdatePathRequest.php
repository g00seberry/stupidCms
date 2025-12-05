<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path;

use App\Http\Requests\Admin\Path\Concerns\PathValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для обновления Path.
 *
 * Валидирует данные для обновления поля blueprint:
 * - name: опциональное имя поля (regex: a-z0-9_)
 * - parent_id: опциональный ID родительского поля
 * - data_type: опциональный тип данных
 * - cardinality: опциональная кардинальность (one/many)
 * - is_indexed: опциональный флаг индексации
 * - sort_order: опциональный порядок сортировки
 * - validation_rules: опциональные правила валидации (массив)
 *   - validation_rules.required: опциональный флаг обязательности поля
 *   - validation_rules.min: опциональное минимальное значение (numeric)
 *   - validation_rules.max: опциональное максимальное значение (numeric)
 *   - validation_rules.pattern: опциональный regex паттерн (string)
 *   - validation_rules.distinct: опциональный флаг уникальности элементов массива (boolean)
 *   - validation_rules.required_if, validation_rules.prohibited_unless, validation_rules.required_unless, validation_rules.prohibited_if: опциональные условные правила (расширенный формат: array с полями 'field', 'value', 'operator')
 *   - validation_rules.field_comparison: опциональное правило сравнения полей (array)
 *   - validation_rules.*: разрешены любые дополнительные ключи
 *
 * @package App\Http\Requests\Admin\Path
 */
class UpdatePathRequest extends FormRequest
{
    use PathValidationRules;

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
     * - data_type: опциональный тип данных (enum)
     * - cardinality: опциональная кардинальность (one/many)
     * - is_indexed: опциональный флаг индексации
     * - sort_order: опциональный порядок сортировки (>= 0)
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
        return array_merge(
            [
                'name' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
                'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:paths,id'],
            ],
            $this->getCommonPathRules(),
            $this->getValidationRulesRules()
        );
    }

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function messages(): array
    {
        return $this->getPathValidationMessages();
    }
}

