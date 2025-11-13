<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для получения списка таксономий в админ-панели.
 *
 * Валидирует параметры фильтрации, поиска, сортировки и пагинации
 * для списка таксономий.
 *
 * @package App\Http\Requests\Admin
 */
class IndexTaxonomiesRequest extends FormRequest
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
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - q: опциональный поисковый запрос (максимум 255 символов)
     * - per_page: опциональное количество на странице (10-100)
     * - sort: опциональная сортировка (created_at, slug, label)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:10|max:100',
            'sort' => [
                'sometimes',
                'string',
                Rule::in([
                    'created_at.desc',
                    'created_at.asc',
                    'slug.asc',
                    'slug.desc',
                    'label.asc',
                    'label.desc',
                ]),
            ],
        ];
    }
}


