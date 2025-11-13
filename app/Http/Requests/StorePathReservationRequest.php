<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для создания резервации пути.
 *
 * Валидирует данные для резервации пути:
 * - path: обязательный путь (максимум 255 символов)
 * - source: обязательный источник резервации (максимум 100 символов)
 * - reason: опциональная причина резервации (максимум 255 символов)
 *
 * @package App\Http\Requests
 */
class StorePathReservationRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Требует прав администратора (is_admin=true).
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - path: обязательный путь (максимум 255 символов)
     * - source: обязательный источник (максимум 100 символов)
     * - reason: опциональная причина (максимум 255 символов)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'path' => 'required|string|max:255',
            'source' => 'required|string|max:100',
            'reason' => 'nullable|string|max:255',
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
            'path.required' => 'The path field is required.',
            'path.max' => 'The path may not be greater than 255 characters.',
            'source.required' => 'The source field is required.',
            'source.max' => 'The source may not be greater than 100 characters.',
            'reason.max' => 'The reason may not be greater than 255 characters.',
        ];
    }
}

