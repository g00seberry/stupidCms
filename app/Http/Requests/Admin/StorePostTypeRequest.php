<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Rules\ReservedSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Request для создания типа записи (PostType).
 *
 * Валидирует данные для создания типа записи:
 * - slug: обязательный уникальный slug (regex, зарезервированные пути)
 * - name: обязательное название (максимум 255 символов)
 * - options_json: опциональный объект настроек
 *
 * @package App\Http\Requests\Admin
 */
class StorePostTypeRequest extends FormRequest
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
     * - slug: обязательный уникальный slug (regex, зарезервированные пути)
     * - name: обязательное название (максимум 255 символов)
     * - options_json: опциональный объект (не массив)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('post_types', 'slug'),
                new ReservedSlug(),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'options_json' => [
                'sometimes',
                'array',
                function ($attribute, $value, $fail) {
                    if ($value === []) {
                        return;
                    }

                    if (array_is_list($value)) {
                        $fail('The options_json field must be an object.');
                    }
                },
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
            'slug.required' => 'The slug field is required.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, underscores, and hyphens.',
            'name.required' => 'The name field is required.',
            'options_json.array' => 'The options_json field must be an object.',
        ];
    }

    /**
     * Настроить валидатор с дополнительной логикой.
     *
     * Warnings не блокируют валидацию, только предупреждают.
     * Реальная передача warnings происходит через метод warnings() в контроллере.
     *
     * @param \Illuminate\Validation\Validator $validator Валидатор
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        // Warnings не блокируют валидацию, только предупреждают
        // Реальная передача warnings происходит через метод warnings() в контроллере
    }

    /**
     * Получить warnings для добавления в meta ответа.
     *
     * @return array<string, array<string>> Массив warnings
     */
    public function warnings(): array
    {
        return [];
    }
}


