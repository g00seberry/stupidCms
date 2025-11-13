<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Request для обновления Blade шаблона.
 *
 * Валидирует данные для обновления шаблона:
 * - content: обязательное содержимое шаблона (разрешается пустая строка)
 *
 * @package App\Http\Requests\Admin
 */
class UpdateTemplateRequest extends FormRequest
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
     * - content: обязательное содержимое шаблона (разрешается пустая строка)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => [
                'required',
                'string',
                // Разрешаем пустую строку - это валидное значение для шаблона
            ],
        ];
    }

    /**
     * Подготовить данные для валидации.
     *
     * Преобразует null в пустую строку, так как Laravel middleware ConvertEmptyStringsToNull
     * преобразует пустые строки в null, но для шаблонов пустая строка - валидное значение.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Если поле content присутствует в запросе, но его значение null
        // (преобразовано из пустой строки middleware ConvertEmptyStringsToNull),
        // преобразуем обратно в пустую строку
        $all = $this->all();
        if (array_key_exists('content', $all) && $all['content'] === null) {
            $this->merge(['content' => '']);
        }
    }



    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function messages(): array
    {
        return [
            'content.required' => 'The content field is required.',
        ];
    }
}

