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
     * - required_if, prohibited_unless, required_unless, prohibited_if: условные правила (расширенный формат)
     * - unique, exists: правила уникальности и существования (расширенный формат)
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
            // Условные правила - только расширенный формат
            'validation_rules.required_if' => ['sometimes', 'array'],
            'validation_rules.required_if.field' => ['required_with:validation_rules.required_if', 'string'],
            'validation_rules.required_if.value' => ['required_with:validation_rules.required_if'],
            'validation_rules.required_if.operator' => ['sometimes', 'string', Rule::in(['==', '!=', '>', '<', '>=', '<='])],
            'validation_rules.prohibited_unless' => ['sometimes', 'array'],
            'validation_rules.prohibited_unless.field' => ['required_with:validation_rules.prohibited_unless', 'string'],
            'validation_rules.prohibited_unless.value' => ['required_with:validation_rules.prohibited_unless'],
            'validation_rules.prohibited_unless.operator' => ['sometimes', 'string', Rule::in(['==', '!=', '>', '<', '>=', '<='])],
            'validation_rules.required_unless' => ['sometimes', 'array'],
            'validation_rules.required_unless.field' => ['required_with:validation_rules.required_unless', 'string'],
            'validation_rules.required_unless.value' => ['required_with:validation_rules.required_unless'],
            'validation_rules.required_unless.operator' => ['sometimes', 'string', Rule::in(['==', '!=', '>', '<', '>=', '<='])],
            'validation_rules.prohibited_if' => ['sometimes', 'array'],
            'validation_rules.prohibited_if.field' => ['required_with:validation_rules.prohibited_if', 'string'],
            'validation_rules.prohibited_if.value' => ['required_with:validation_rules.prohibited_if'],
            'validation_rules.prohibited_if.operator' => ['sometimes', 'string', Rule::in(['==', '!=', '>', '<', '>=', '<='])],
            // Правила уникальности и существования - только расширенный формат
            'validation_rules.unique' => ['sometimes', 'array'],
            'validation_rules.unique.table' => ['required_with:validation_rules.unique', 'string'],
            'validation_rules.unique.column' => ['sometimes', 'string'],
            'validation_rules.unique.except' => ['sometimes', 'array'],
            'validation_rules.unique.except.column' => ['required_with:validation_rules.unique.except', 'string'],
            'validation_rules.unique.except.value' => ['required_with:validation_rules.unique.except'],
            'validation_rules.unique.where' => ['sometimes', 'array'],
            'validation_rules.unique.where.column' => ['required_with:validation_rules.unique.where', 'string'],
            'validation_rules.unique.where.value' => ['required_with:validation_rules.unique.where'],
            'validation_rules.exists' => ['sometimes', 'array'],
            'validation_rules.exists.table' => ['required_with:validation_rules.exists', 'string'],
            'validation_rules.exists.column' => ['sometimes', 'string'],
            'validation_rules.exists.where' => ['sometimes', 'array'],
            'validation_rules.exists.where.column' => ['required_with:validation_rules.exists.where', 'string'],
            'validation_rules.exists.where.value' => ['required_with:validation_rules.exists.where'],
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

