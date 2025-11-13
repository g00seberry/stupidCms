<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для создания Blade шаблона.
 *
 * Валидирует данные для создания шаблона:
 * - name: обязательное имя шаблона (regex, максимум 255 символов)
 * - content: обязательное содержимое шаблона
 *
 * @package App\Http\Requests\Admin
 */
class StoreTemplateRequest extends FormRequest
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
     * - name: обязательное имя шаблона (regex, максимум 255 символов)
     * - content: обязательное содержимое шаблона
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9._-]+$/i',
            ],
            'content' => [
                'required',
                'string',
            ],
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
            'name.required' => 'The name field is required.',
            'name.regex' => 'The name may only contain letters, numbers, dots, underscores, and hyphens.',
            'content.required' => 'The content field is required.',
        ];
    }
}

