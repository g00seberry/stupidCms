<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для поиска записей (entries) в админ-панели.
 *
 * Валидирует параметры поиска по заголовку и фильтрации по типам записей.
 * Поддерживает пагинацию результатов.
 *
 * @package App\Http\Requests\Admin
 */
class SearchEntriesRequest extends FormRequest
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
     * - filters.title: опциональный поисковый запрос по заголовку (максимум 500 символов)
     * - filters.post_type_ids: опциональный массив ID типов записей (должны существовать)
     * - pagination.per_page: опциональное количество на странице (10-100)
     * - pagination.page: опциональный номер страницы (>=1)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filters' => 'nullable|array',
            'filters.title' => 'nullable|string|max:500',
            'filters.post_type_ids' => 'nullable|array',
            'filters.post_type_ids.*' => 'integer|exists:post_types,id',
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
            'filters.title.max' => 'The title may not be greater than 500 characters.',
            'filters.post_type_ids.*.exists' => 'One or more specified post types do not exist.',
            'pagination.per_page.min' => 'Results per page must be at least 10.',
            'pagination.per_page.max' => 'Results per page must not exceed 100.',
            'pagination.page.min' => 'Page number must be at least 1.',
        ];
    }
}

