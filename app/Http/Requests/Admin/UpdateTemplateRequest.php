<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
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
     * Подготовка данных для валидации.
     * Преобразуем null в пустую строку, так как Laravel middleware ConvertEmptyStringsToNull
     * преобразует пустые строки в null, но для шаблонов пустая строка - валидное значение.
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => 'The content field is required.',
        ];
    }
}

