<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для получения списка термов в админ-панели.
 *
 * Валидирует параметры фильтрации, поиска, сортировки и пагинации
 * для списка термов таксономии.
 *
 * @package App\Http\Requests\Admin
 */
class IndexTermsRequest extends FormRequest
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
        return true;
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
        if ($this->has('q') || $this->has('sort')) {
            $data['filters'] = [];
            
            if ($this->has('q')) {
                $data['filters']['q'] = $this->input('q');
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
     * - filters.q: опциональный поисковый запрос (максимум 255 символов)
     * - filters.sort: опциональная сортировка (created_at, name)
     * - pagination.per_page: опциональное количество на странице (10-100)
     * - pagination.page: опциональный номер страницы
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filters' => 'nullable|array',
            'filters.q' => 'nullable|string|max:255',
            'filters.sort' => [
                'nullable',
                'string',
                Rule::in([
                    'created_at.desc',
                    'created_at.asc',
                    'name.asc',
                    'name.desc',
                ]),
            ],
            'pagination' => 'nullable|array',
            'pagination.per_page' => 'nullable|integer|min:10|max:100',
            'pagination.page' => 'nullable|integer|min:1',
        ];
    }
}


