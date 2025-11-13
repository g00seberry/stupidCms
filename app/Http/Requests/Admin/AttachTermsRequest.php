<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для привязки термов к записи.
 *
 * Валидирует массив ID термов для привязки к записи:
 * - term_ids: обязательный массив ID термов (минимум 1 элемент)
 * - Все термы должны существовать и не быть удалёнными
 *
 * @package App\Http\Requests\Admin
 */
class AttachTermsRequest extends FormRequest
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
     * - term_ids: обязательный массив ID термов (минимум 1 элемент, уникальные значения)
     * - Все термы должны существовать и не быть удалёнными
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term_ids' => 'required|array|min:1',
            'term_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('terms', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}


