<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для входа в систему (аутентификации).
 *
 * Валидирует email и password для входа администратора.
 *
 * @package App\Http\Requests\Auth
 */
class LoginRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Публичный запрос, доступен всем (авторизация происходит после валидации).
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
     * - email: обязательный, строгий формат email, lowercase, максимум 254 символа
     * - password: обязательный, строка, минимум 8 символов, максимум 200 символов
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:strict', 'lowercase', 'max:254'],
            'password' => ['required', 'string', 'min:8', 'max:200'],
        ];
    }
}

