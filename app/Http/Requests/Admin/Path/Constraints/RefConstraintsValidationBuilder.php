<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path\Constraints;

use App\Models\Path;
use Illuminate\Validation\Rule;

/**
 * Билдер правил валидации constraints для ref-полей.
 *
 * Строит правила валидации для constraints полей с data_type='ref'.
 * Поддерживает новый плоский формат: constraints.allowed_post_type_ids.
 *
 * @package App\Http\Requests\Admin\Path\Constraints
 */
final class RefConstraintsValidationBuilder extends AbstractConstraintsValidationBuilder
{
    /**
     * Получить тип данных, который поддерживает этот билдер.
     *
     * @return string
     */
    public function getSupportedDataType(): string
    {
        return 'ref';
    }

    /**
     * Построить правила валидации для ref-полей при создании.
     *
     * Правила для нового формата:
     * - constraints.allowed_post_type_ids: обязателен для ref-полей, массив, минимум 1 элемент
     * - constraints.allowed_post_type_ids.*: integer, distinct, exists в post_types
     * - constraints.*: запрещены для остальных полей (обрабатывается базовым классом)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function buildRulesForSupportedDataType(): array
    {
        return array_merge(
            $this->getBaseConstraintsArrayRule(),
            [
                'constraints.allowed_post_type_ids' => [
                    'required',
                    'array',
                    'min:1',
                ],
                'constraints.allowed_post_type_ids.*' => [
                    'integer',
                    'distinct',
                    Rule::exists('post_types', 'id'),
                ],
            ]
        );
    }

    /**
     * Построить правила валидации для ref-полей при обновлении.
     *
     * Правила для нового формата:
     * - constraints.allowed_post_type_ids: опционален (sometimes), но если передан - массив, минимум 1 элемент
     * - constraints.allowed_post_type_ids.*: integer, distinct, exists в post_types
     *
     * @param Path|null $path Текущий Path из route
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function buildUpdateRulesForSupportedDataType(?Path $path): array
    {
        return array_merge(
            $this->getBaseConstraintsArrayRule(),
            [
                'constraints.allowed_post_type_ids' => [
                    'sometimes',
                    'array',
                    'min:1',
                ],
                'constraints.allowed_post_type_ids.*' => [
                    'integer',
                    'distinct',
                    Rule::exists('post_types', 'id'),
                ],
            ]
        );
    }

    /**
     * Получить кастомные сообщения для ошибок валидации constraints.
     *
     * Сообщения соответствуют новому формату без вложенности ref.
     *
     * @return array<string, string>
     */
    public function buildMessages(): array
    {
        return [
            'constraints.allowed_post_type_ids.required' => 'Поле constraints.allowed_post_type_ids обязательно для полей с типом данных "ref".',
            'constraints.allowed_post_type_ids.array' => 'Поле constraints.allowed_post_type_ids должно быть массивом.',
            'constraints.allowed_post_type_ids.min' => 'Поле constraints.allowed_post_type_ids должно содержать хотя бы один элемент.',
            'constraints.allowed_post_type_ids.*.integer' => 'Все элементы в constraints.allowed_post_type_ids должны быть целыми числами.',
            'constraints.allowed_post_type_ids.*.distinct' => 'В constraints.allowed_post_type_ids не должно быть повторяющихся значений.',
            'constraints.allowed_post_type_ids.*.exists' => 'Один или несколько указанных типов записей не существуют.',
        ];
    }
}

