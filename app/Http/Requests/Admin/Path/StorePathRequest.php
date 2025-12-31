<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path;

use App\Http\Requests\Admin\Path\Concerns\PathConstraintsValidationRules;
use App\Http\Requests\Admin\Path\Concerns\PathValidationRules;
use App\Models\Blueprint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для создания Path.
 *
 * Валидирует данные для создания поля blueprint:
 * - name: обязательное имя поля (regex: a-z0-9_)
 * - parent_id: опциональный ID родительского поля (должен принадлежать тому же blueprint)
 * - data_type: обязательный тип данных
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
 * - constraints: опциональные ограничения для полей (массив)
 *   Формат зависит от data_type:
 *   - Для data_type='ref': constraints.allowed_post_type_ids (массив ID допустимых типов записей, обязателен для ref-полей)
 *   - Для data_type='media': constraints.allowed_mimes (будущая поддержка)
 *   - Для других типов данных: constraints запрещены
 *
 * @package App\Http\Requests\Admin\Path
 */
class StorePathRequest extends FormRequest
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
     * - name: обязательное имя поля (regex: a-z0-9_)
     * - parent_id: опциональный ID родительского поля (существующий path)
     * - data_type: обязательный тип данных (enum)
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
     *   - validation_rules.field_comparison: опциональное правило сравнения полей (array)
     *   - validation_rules.*: разрешены любые дополнительные ключи
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $commonRules = $this->getCommonPathRules();
        
        // Получаем blueprint из route для правила валидации parent_id
        $blueprint = $this->route('blueprint');
        $blueprintId = ($blueprint instanceof Blueprint) ? $blueprint->id : 0;
        
        return array_merge(
            [
                'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
                'parent_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('paths', 'id')->where('blueprint_id', $blueprintId),
                ],
                'data_type' => ['required', Rule::in(['string', 'text', 'int', 'float', 'bool', 'datetime', 'json', 'ref'])],
            ],
            [
                'cardinality' => $commonRules['cardinality'],
                'is_indexed' => $commonRules['is_indexed'],
                'sort_order' => $commonRules['sort_order'],
            ],
            $this->getValidationRulesRules(),
            $this->getConstraintsRulesForStore()
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

