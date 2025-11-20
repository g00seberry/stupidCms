<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для создания Path.
 *
 * Валидирует данные для создания поля blueprint:
 * - name: обязательное имя поля (regex: a-z0-9_)
 * - parent_id: опциональный ID родительского поля
 * - data_type: обязательный тип данных
 * - cardinality: опциональная кардинальность (one/many)
 * - is_required: опциональный флаг обязательности
 * - is_indexed: опциональный флаг индексации
 * - sort_order: опциональный порядок сортировки
 * - validation_rules: опциональные правила валидации (массив)
 *
 * @package App\Http\Requests\Admin\Path
 */
class StorePathRequest extends FormRequest
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
     * - name: обязательное имя поля (regex: a-z0-9_)
     * - parent_id: опциональный ID родительского поля (существующий path)
     * - data_type: обязательный тип данных (enum)
     * - cardinality: опциональная кардинальность (one/many)
     * - is_required: опциональный флаг обязательности
     * - is_indexed: опциональный флаг индексации
     * - sort_order: опциональный порядок сортировки (>= 0)
     * - validation_rules: опциональные правила валидации (массив)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
            'parent_id' => ['nullable', 'integer', 'exists:paths,id'],
            'data_type' => ['required', Rule::in(['string', 'text', 'int', 'float', 'bool', 'date', 'datetime', 'json', 'ref'])],
            'cardinality' => ['sometimes', Rule::in(['one', 'many'])],
            'is_required' => ['sometimes', 'boolean'],
            'is_indexed' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'validation_rules' => ['nullable', 'array'],
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
            'name.regex' => 'Имя поля может содержать только строчные буквы, цифры и подчёркивания.',
        ];
    }
}

