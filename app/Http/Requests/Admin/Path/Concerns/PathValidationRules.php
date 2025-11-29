<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path\Concerns;

use Illuminate\Validation\Rule;

/**
 * Трейт с общими правилами валидации для Path.
 *
 * Содержит правила валидации, общие для StorePathRequest и UpdatePathRequest.
 *
 * @package App\Http\Requests\Admin\Path\Concerns
 */
trait PathValidationRules
{
    /**
     * Получить правила валидации для validation_rules.
     *
     * Возвращает массив правил валидации для всех поддерживаемых полей в validation_rules:
     * - required: флаг обязательности поля
     * - min, max: минимальное и максимальное значение
     * - pattern: regex паттерн
     * - array_min_items, array_max_items: ограничения для массивов
     * - array_unique: флаг уникальности элементов массива
     * - required_if, prohibited_unless, required_unless, prohibited_if: условные правила
     * - unique, exists: правила уникальности и существования
     * - field_comparison: правило сравнения полей
     * - *: разрешены любые дополнительные ключи
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function getValidationRulesRules(): array
    {
        return [
            'validation_rules' => ['nullable', 'array'],
            'validation_rules.required' => ['sometimes', 'boolean'],
            'validation_rules.min' => ['sometimes', 'numeric'],
            'validation_rules.max' => ['sometimes', 'numeric'],
            'validation_rules.pattern' => ['sometimes', 'string'],
            'validation_rules.array_min_items' => ['sometimes', 'numeric', 'min:0'],
            'validation_rules.array_max_items' => ['sometimes', 'numeric', 'min:0'],
            'validation_rules.array_unique' => ['sometimes', 'boolean'],
            'validation_rules.required_if' => ['sometimes'],
            'validation_rules.prohibited_unless' => ['sometimes'],
            'validation_rules.required_unless' => ['sometimes'],
            'validation_rules.prohibited_if' => ['sometimes'],
            'validation_rules.unique' => ['sometimes'],
            'validation_rules.exists' => ['sometimes'],
            'validation_rules.field_comparison' => ['sometimes', 'array'],
            'validation_rules.*' => ['nullable'],
        ];
    }

    /**
     * Получить правила валидации для общих полей Path.
     *
     * Возвращает правила валидации для полей, общих для создания и обновления:
     * - data_type: тип данных
     * - cardinality: кардинальность
     * - is_indexed: флаг индексации
     * - sort_order: порядок сортировки
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function getCommonPathRules(): array
    {
        return [
            'data_type' => ['sometimes', Rule::in(['string', 'text', 'int', 'float', 'bool', 'date', 'datetime', 'json', 'ref'])],
            'cardinality' => ['sometimes', Rule::in(['one', 'many'])],
            'is_indexed' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    protected function getPathValidationMessages(): array
    {
        return [
            'name.regex' => 'Имя поля может содержать только строчные буквы, цифры и подчёркивания.',
        ];
    }
}

