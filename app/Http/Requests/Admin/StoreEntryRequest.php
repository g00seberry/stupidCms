<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\BlueprintValidationTrait;
use App\Models\PostType;
use App\Rules\Publishable;
use App\Rules\ReservedSlug;
use App\Rules\UniqueEntrySlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Request для создания новой записи (Entry).
 *
 * Валидирует данные для создания записи контента:
 * - Обязательные: post_type_id, title
 * - Опциональные: slug (автогенерация), content_json, meta_json, published_at
 * - Проверяет глобальную уникальность slug
 * - Проверяет зарезервированные пути
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
     * - slug: опциональный slug (regex, глобальная уникальность, зарезервированные пути)
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
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/',
                new UniqueEntrySlug(),
                new ReservedSlug(),
                (new Publishable())->setData($this->all()),
            ],
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
     * Проверяет, что при публикации записи указан валидный slug.
     * Добавляет динамические правила валидации для content_json из Blueprint.
     *
     * @param \Illuminate\Validation\Validator $validator Валидатор
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        // Добавляем правила валидации для content_json из Blueprint
        $this->addBlueprintValidationRules($validator);

        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_published')) {
                return;
            }

            if ($this->has('slug') && trim((string) $this->input('slug')) === '') {
                $validator->errors()->add('slug', 'A valid slug is required when publishing an entry.');
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
        return [
            'post_type_id.required' => 'The post type id field is required.',
            'post_type_id.exists' => 'The specified post type does not exist.',
            'title.required' => 'The title field is required.',
            'title.max' => 'The title may not be greater than 500 characters.',
            'slug.regex' => 'The slug format is invalid. Only lowercase letters, numbers, and hyphens are allowed.',
            'slug.max' => 'The slug may not be greater than 255 characters.',
            'published_at.date' => 'The published date must be a valid date.',
            'term_ids.array' => 'The term_ids field must be an array.',
            'term_ids.*.exists' => 'One or more specified terms do not exist.',
        ];
    }

}

