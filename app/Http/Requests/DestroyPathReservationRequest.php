<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для удаления резервации пути.
 *
 * Валидирует данные для освобождения зарезервированного пути:
 * - source: обязательный источник резервации (максимум 100 символов)
 * - path: опциональный путь (из route параметра или body, максимум 255 символов)
 *
 * @package App\Http\Requests
 */
class DestroyPathReservationRequest extends FormRequest
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
     * - source: обязательный источник (максимум 100 символов)
     * - path: опциональный путь (из route параметра или body, максимум 255 символов)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source' => 'required|string|max:100',
            'path' => 'nullable|string|max:255', // Опционально из body, если не в URL
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
            'source.required' => 'The source field is required.',
            'source.max' => 'The source may not be greater than 100 characters.',
            'path.max' => 'The path may not be greater than 255 characters.',
        ];
    }

    /**
     * Получить путь из route параметра или body запроса.
     *
     * @return string Путь резервации
     */
    public function getPath(): string
    {
        return $this->route('path') ?? $this->input('path', '');
    }
}

