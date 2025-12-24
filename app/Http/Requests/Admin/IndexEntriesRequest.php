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
     * Подготовить данные для валидации.
     *
     * Преобразует плоские параметры запроса в вложенную структуру для совместимости с тестами.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        // Преобразуем плоские параметры в filters
        if ($this->has('q') || $this->has('post_type_id') || $this->has('status') || $this->has('author_id') || $this->has('sort')) {
            $data['filters'] = [];
            
            if ($this->has('q')) {
                $data['filters']['q'] = $this->input('q');
            }
            if ($this->has('post_type_id')) {
                $data['filters']['post_type_id'] = $this->input('post_type_id');
            }
            if ($this->has('status')) {
                $data['filters']['status'] = $this->input('status');
            }
            if ($this->has('author_id')) {
                $data['filters']['author_id'] = $this->input('author_id');
            }
            if ($this->has('sort')) {
                $data['filters']['sort'] = $this->input('sort');
            }
        }

        // Преобразуем плоские параметры в pagination
        if ($this->has('per_page') || $this->has('page')) {
            $data['pagination'] = [];
            
            if ($this->has('per_page')) {
                $data['pagination']['per_page'] = $this->input('per_page');
            }
            if ($this->has('page')) {
                $data['pagination']['page'] = $this->input('page');
            }
        }

        if (! empty($data)) {
            $this->merge($data);
        }
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - filters.post_type_id: опциональный ID типа записи (должен существовать)
     * - filters.status: опциональный статус (all, draft, published, scheduled, trashed)
     * - filters.q: опциональный поисковый запрос (максимум 500 символов)
     * - filters.author_id: опциональный ID автора (должен существовать)
     * - filters.term: опциональный массив ID термов (должны существовать)
     * - filters.date_from/filters.date_to: опциональные даты для фильтрации
     * - filters.date_field: опциональное поле даты (updated, published)
     * - filters.sort: опциональная сортировка
     * - pagination.per_page: опциональное количество на странице (10-100)
     * - pagination.page: опциональный номер страницы
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filters' => 'nullable|array',
            'filters.post_type_id' => 'nullable|integer|exists:post_types,id',
            'filters.status' => 'nullable|string|in:all,draft,published,scheduled,trashed',
            'filters.q' => 'nullable|string|max:500',
            'filters.author_id' => 'nullable|integer|exists:users,id',
            'filters.term' => 'nullable|array',
            'filters.term.*' => 'integer|exists:terms,id',
            'filters.date_from' => 'nullable|date',
            'filters.date_to' => 'nullable|date|after_or_equal:filters.date_from',
            'filters.date_field' => 'nullable|string|in:updated,published',
            'filters.sort' => 'nullable|string|in:updated_at.desc,updated_at.asc,published_at.desc,published_at.asc,title.asc,title.desc',
            'pagination' => 'nullable|array',
            'pagination.per_page' => 'nullable|integer|min:10|max:100',
            'pagination.page' => 'nullable|integer|min:1',
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
            'filters.post_type_id.exists' => 'The specified post type does not exist.',
            'filters.status.in' => 'Invalid status value.',
            'filters.author_id.exists' => 'The specified author does not exist.',
            'filters.term.*.exists' => 'One or more specified terms do not exist.',
            'filters.date_to.after_or_equal' => 'The end date must be after or equal to the start date.',
            'filters.date_field.in' => 'Invalid date field. Must be "updated" or "published".',
            'filters.sort.in' => 'Invalid sort field.',
            'pagination.per_page.min' => 'Results per page must be at least 10.',
            'pagination.per_page.max' => 'Results per page must not exceed 100.',
            'pagination.page.min' => 'Page number must be at least 1.',
        ];
    }
}

