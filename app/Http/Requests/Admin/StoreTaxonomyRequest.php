<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для создания таксономии (Taxonomy).
 *
 * Валидирует данные для создания таксономии:
 * - label: обязательное название (максимум 255 символов)
 * - hierarchical: опциональный boolean для иерархической таксономии
 * - options_json: опциональный объект настроек
 *
 * @package App\Http\Requests\Admin
 */
class StoreTaxonomyRequest extends FormRequest
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
     * - label: обязательное название (максимум 255 символов)
     * - hierarchical: опциональный boolean
     * - options_json: опциональный объект
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => 'required|string|max:255',
            'hierarchical' => 'sometimes|boolean',
            'options_json' => 'sometimes|array',
        ];
    }
}


