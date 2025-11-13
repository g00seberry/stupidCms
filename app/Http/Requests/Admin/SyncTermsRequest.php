<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для синхронизации термов записи.
 *
 * Валидирует массив ID термов для синхронизации привязки записи:
 * - term_ids: обязательный массив ID термов (может быть пустым)
 * - Все термы должны существовать и не быть удалёнными
 *
 * @package App\Http\Requests\Admin
 */
class SyncTermsRequest extends FormRequest
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
     * - term_ids: обязательный массив ID термов (может быть пустым, уникальные значения)
     * - Все термы должны существовать и не быть удалёнными
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term_ids' => 'required|array',
            'term_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('terms', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}


