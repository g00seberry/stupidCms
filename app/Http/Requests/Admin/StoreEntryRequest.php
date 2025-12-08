<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\BlueprintValidationTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Request для создания новой записи (Entry).
 *
 * Валидирует данные для создания записи контента:
 * - Обязательные: post_type_id, title
 * - Опциональные: content_json, meta_json, published_at
 *
 * @package App\Http\Requests\Admin
 */
class StoreEntryRequest extends FormRequest
{
    use BlueprintValidationTrait;
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Авторизация обрабатывается middleware маршрута.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by route middleware
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - post_type_id: обязательный ID типа записи (должен существовать)
     * - title: обязательный заголовок (максимум 500 символов)
     * - content_json: опциональный JSON массив (валидируется по правилам Blueprint, если привязан)
     * - meta_json: опциональный JSON массив
     * - is_published: опциональный boolean
     * - published_at: опциональная дата публикации
     * - template_override: опциональный шаблон
     * - term_ids: опциональный массив ID термов
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'post_type_id' => 'required|integer|exists:post_types,id',
            'title' => 'required|string|max:500',
            'content_json' => ['nullable', 'array'],
            'meta_json' => 'nullable|array',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'template_override' => 'nullable|string|max:255',
            'term_ids' => 'nullable|array',
            'term_ids.*' => 'integer|exists:terms,id',
        ];
    }

    /**
     * Подготовить данные для валидации.
     *
     * Автоматически устанавливает published_at в текущее время,
     * если is_published=true и published_at не указан.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->boolean('is_published') && ! $this->has('published_at')) {
            $this->merge([
                'published_at' => now()->toDateTimeString(),
            ]);
        }
    }

    /**
     * Настроить валидатор с дополнительной логикой.
     *
     * Добавляет динамические правила валидации для content_json из Blueprint.
     *
     * @param \Illuminate\Validation\Validator $validator Валидатор
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        // Добавляем правила валидации для content_json из Blueprint
        $this->addBlueprintValidationRules($validator);
    }

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function messages(): array
    {
        return [
            'post_type_id.required' => 'The post type id field is required.',
            'post_type_id.exists' => 'The specified post type does not exist.',
            'title.required' => 'The title field is required.',
            'title.max' => 'The title may not be greater than 500 characters.',
            'published_at.date' => 'The published date must be a valid date.',
            'term_ids.array' => 'The term_ids field must be an array.',
            'term_ids.*.exists' => 'One or more specified terms do not exist.',
        ];
    }

}

