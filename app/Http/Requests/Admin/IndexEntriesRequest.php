<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для получения списка записей (entries) в админ-панели.
 *
 * Валидирует параметры фильтрации, поиска, сортировки и пагинации
 * для списка записей контента.
 *
 * @package App\Http\Requests\Admin
 */
class IndexEntriesRequest extends FormRequest
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
        return true; // Authorization handled by route middleware
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - post_type: опциональный slug типа записи (должен существовать)
     * - status: опциональный статус (all, draft, published, scheduled, trashed)
     * - q: опциональный поисковый запрос (максимум 500 символов)
     * - author_id: опциональный ID автора (должен существовать)
     * - term: опциональный массив ID термов (должны существовать)
     * - date_from/date_to: опциональные даты для фильтрации
     * - date_field: опциональное поле даты (updated, published)
     * - sort: опциональная сортировка
     * - per_page: опциональное количество на странице (10-100)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'post_type' => 'nullable|string|exists:post_types,slug',
            'status' => 'nullable|string|in:all,draft,published,scheduled,trashed',
            'q' => 'nullable|string|max:500',
            'author_id' => 'nullable|integer|exists:users,id',
            'term' => 'nullable|array',
            'term.*' => 'integer|exists:terms,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'date_field' => 'nullable|string|in:updated,published',
            'sort' => 'nullable|string|in:updated_at.desc,updated_at.asc,published_at.desc,published_at.asc,title.asc,title.desc',
            'per_page' => 'nullable|integer|min:10|max:100',
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
            'post_type.exists' => 'The specified post type does not exist.',
            'status.in' => 'Invalid status value.',
            'author_id.exists' => 'The specified author does not exist.',
            'term.*.exists' => 'One or more specified terms do not exist.',
            'date_to.after_or_equal' => 'The end date must be after or equal to the start date.',
            'date_field.in' => 'Invalid date field. Must be "updated" or "published".',
            'sort.in' => 'Invalid sort field.',
            'per_page.min' => 'Results per page must be at least 10.',
            'per_page.max' => 'Results per page must not exceed 100.',
        ];
    }
}

