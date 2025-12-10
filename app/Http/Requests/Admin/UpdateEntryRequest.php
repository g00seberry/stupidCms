<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\BlueprintValidationTrait;
use App\Models\Entry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Request для обновления записи (Entry).
 *
 * Валидирует данные для обновления записи контента:
 * - Все поля опциональны (sometimes)
 *
 * @package App\Http\Requests\Admin
 */
class UpdateEntryRequest extends FormRequest
{
    use BlueprintValidationTrait;
    /**
     * @var \App\Models\Entry|null Кэшированная модель записи
     */
    private ?Entry $entryModel = null;

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
     * Валидирует (все поля опциональны):
     * - title: заголовок (максимум 500 символов)
     * - data_json: опциональный JSON массив (валидируется по правилам Blueprint, если привязан)
     * - is_published: boolean
     * - published_at: дата публикации
     * - template_override: шаблон
     * - term_ids: массив ID термов
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $entryId = $this->route('id');
        // Используем eager loading для избежания N+1 запросов
        $entry = Entry::query()
            ->withTrashed()
            ->with('postType.blueprint')
            ->find($entryId);
        $this->entryModel = $entry;
        
        if (! $entry) {
            return [];
        }

        return [
            'title' => 'sometimes|required|string|max:500',
            'data_json' => ['sometimes', 'nullable', 'array'],
            'is_published' => 'sometimes|boolean',
            'published_at' => 'sometimes|nullable|date',
            'template_override' => 'sometimes|nullable|string|max:255',
            'term_ids' => 'sometimes|nullable|array',
            'term_ids.*' => 'integer|exists:terms,id',
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
            'title.required' => 'The title field is required.',
            'title.max' => 'The title may not be greater than 500 characters.',
            'published_at.date' => 'The published date must be a valid date.',
            'term_ids.array' => 'The term_ids field must be an array.',
            'term_ids.*.exists' => 'One or more specified terms do not exist.',
        ];
    }

    /**
     * Настроить валидатор с дополнительной логикой.
     *
     * Добавляет динамические правила валидации для data_json из Blueprint.
     *
     * @param \Illuminate\Validation\Validator $validator Валидатор
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $entry = $this->entryModel;

        if (! $entry) {
            return;
        }

        // Добавляем правила валидации для data_json из Blueprint
        $this->addBlueprintValidationRules($validator, $entry->postType);
    }

}

