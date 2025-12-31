<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Path\Constraints;

use App\Models\Path;
use Illuminate\Validation\Rule;

/**
 * Билдер правил валидации constraints для media-полей.
 *
 * Строит правила валидации для constraints полей с data_type='media'.
 * Поддерживает новый плоский формат: constraints.allowed_mimes.
 *
 * @package App\Http\Requests\Admin\Path\Constraints
 */
final class MediaConstraintsValidationBuilder extends AbstractConstraintsValidationBuilder
{
    /**
     * Получить тип данных, который поддерживает этот билдер.
     *
     * @return string
     */
    public function getSupportedDataType(): string
    {
        return 'media';
    }

    /**
     * Получить список допустимых MIME-типов из конфигурации.
     *
     * @return array<string> Массив MIME-типов
     */
    private function getAllowedMimeTypes(): array
    {
        return config('media.allowed_mimes', []);
    }

    /**
     * Построить правила валидации для media-полей при создании.
     *
     * Правила для нового формата:
     * - constraints.allowed_mimes: обязателен для media-полей, массив, минимум 1 элемент
     * - constraints.allowed_mimes.*: string, distinct, должен быть одним из допустимых MIME-типов
     * - constraints.*: запрещены для остальных полей (обрабатывается базовым классом)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function buildRulesForSupportedDataType(): array
    {
        $allowedMimes = $this->getAllowedMimeTypes();

        return array_merge(
            $this->getBaseConstraintsArrayRule(),
            [
                'constraints.allowed_mimes' => [
                    'required',
                    'array',
                    'min:1',
                ],
                'constraints.allowed_mimes.*' => [
                    'string',
                    'distinct',
                    Rule::in($allowedMimes),
                ],
            ]
        );
    }

    /**
     * Построить правила валидации для media-полей при обновлении.
     *
     * Правила для нового формата:
     * - constraints.allowed_mimes: опционален (sometimes), но если передан - массив, минимум 1 элемент
     * - constraints.allowed_mimes.*: string, distinct, должен быть одним из допустимых MIME-типов
     *
     * @param Path|null $path Текущий Path из route
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function buildUpdateRulesForSupportedDataType(?Path $path): array
    {
        $allowedMimes = $this->getAllowedMimeTypes();

        return array_merge(
            $this->getBaseConstraintsArrayRule(),
            [
                'constraints.allowed_mimes' => [
                    'sometimes',
                    'array',
                    'min:1',
                ],
                'constraints.allowed_mimes.*' => [
                    'string',
                    'distinct',
                    Rule::in($allowedMimes),
                ],
            ]
        );
    }

    /**
     * Получить кастомные сообщения для ошибок валидации constraints.
     *
     * Сообщения соответствуют новому формату без вложенности media.
     *
     * @return array<string, string>
     */
    public function buildMessages(): array
    {
        return [
            'constraints.allowed_mimes.required' => 'Поле constraints.allowed_mimes обязательно для полей с типом данных "media".',
            'constraints.allowed_mimes.array' => 'Поле constraints.allowed_mimes должно быть массивом.',
            'constraints.allowed_mimes.min' => 'Поле constraints.allowed_mimes должно содержать хотя бы один элемент.',
            'constraints.allowed_mimes.*.string' => 'Все элементы в constraints.allowed_mimes должны быть строками.',
            'constraints.allowed_mimes.*.distinct' => 'В constraints.allowed_mimes не должно быть повторяющихся значений.',
            'constraints.allowed_mimes.*.in' => 'Один или несколько указанных MIME-типов не разрешены. Разрешённые типы: ' . implode(', ', $this->getAllowedMimeTypes()),
        ];
    }
}

