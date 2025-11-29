<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path;

use App\Http\Requests\Admin\Path\Concerns\PathValidationRules;
use App\Models\Blueprint;
use App\Models\Path;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
 *   - validation_rules.array_min_items: опциональное минимальное количество элементов массива (numeric)
 *   - validation_rules.array_max_items: опциональное максимальное количество элементов массива (numeric)
 *   - validation_rules.array_unique: опциональный флаг уникальности элементов массива (boolean)
 *   - validation_rules.required_if, validation_rules.prohibited_unless, validation_rules.required_unless, validation_rules.prohibited_if: опциональные условные правила (расширенный формат: array с полями 'field', 'value', 'operator')
 *   - validation_rules.unique: опциональное правило уникальности (расширенный формат: array с обязательным полем 'table')
 *   - validation_rules.exists: опциональное правило существования (расширенный формат: array с обязательным полем 'table')
 *   - validation_rules.field_comparison: опциональное правило сравнения полей (array)
 *   - validation_rules.*: разрешены любые дополнительные ключи
 *
 * @package App\Http\Requests\Admin\Path
 */
class StorePathRequest extends FormRequest
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
     *   - validation_rules.array_min_items: опциональное минимальное количество элементов массива (numeric)
     *   - validation_rules.array_max_items: опциональное максимальное количество элементов массива (numeric)
     *   - validation_rules.array_unique: опциональный флаг уникальности элементов массива (boolean)
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
        $commonRules = $this->getCommonPathRules();
        
        return array_merge(
            [
                'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
                'parent_id' => ['nullable', 'integer', 'exists:paths,id'],
                'data_type' => ['required', Rule::in(['string', 'text', 'int', 'float', 'bool', 'date', 'datetime', 'json', 'ref'])],
            ],
            [
                'cardinality' => $commonRules['cardinality'],
                'is_indexed' => $commonRules['is_indexed'],
                'sort_order' => $commonRules['sort_order'],
            ],
            $this->getValidationRulesRules()
        );
    }

    /**
     * Настроить валидатор экземпляра.
     *
     * Добавляет проверку, что parent_id принадлежит тому же blueprint.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $blueprint = $this->route('blueprint');
            $parentId = $this->input('parent_id');

            if ($parentId === null || !($blueprint instanceof Blueprint)) {
                return;
            }

            $parentPath = Path::find($parentId);
            if ($parentPath === null) {
                return; // Ошибка уже будет обработана правилом exists
            }

            if ($parentPath->blueprint_id !== $blueprint->id) {
                $validator->errors()->add(
                    'parent_id',
                    "Родительское поле должно принадлежать тому же blueprint '{$blueprint->code}'."
                );
            }
        });
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

