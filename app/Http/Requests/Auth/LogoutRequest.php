<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для выхода из системы.
 *
 * Валидирует опциональный параметр 'all' для отзыва всех refresh токенов.
 *
 * @package App\Http\Requests\Auth
 */
final class LogoutRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Требует аутентификации через middleware маршрута.
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
     * - all: опциональный boolean для отзыва всех refresh токенов (по умолчанию отзывается только текущий)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'all' => ['sometimes', 'boolean'],
        ];
    }
}


