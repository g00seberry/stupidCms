<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\TemplatePathRule;
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
     * - name: обязательное имя шаблона (должно быть в папке templates, максимум 255 символов)
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
                new TemplatePathRule(app(\App\Domain\View\TemplatePathValidator::class)),
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
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'content.required' => 'The content field is required.',
        ];
    }
}

