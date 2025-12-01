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
     * - distinct: флаг уникальности элементов массива
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
            'validation_rules.distinct' => ['sometimes', 'boolean'],
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

