<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Blueprint;

class StorePathRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для этого запроса.
     */
    public function authorize(): bool
    {
        return true; // TODO: добавить проверку прав доступа
    }

    /**
     * Правила валидации для запроса.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $blueprint = $this->route('blueprint');
        $pathId = $this->route('path')?->id;

        return [
            'blueprint_id' => [
                'required',
                'integer',
                'exists:blueprints,id',
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/',
            ],
            'full_path' => [
                'required',
                'string',
                'max:500',
                // Уникальность full_path в рамках Blueprint
                function ($attribute, $value, $fail) use ($blueprint, $pathId) {
                    if (!$blueprint) {
                        return;
                    }

                    $query = $blueprint->paths()
                        ->where('full_path', $value);

                    if ($pathId) {
                        $query->where('id', '!=', $pathId);
                    }

                    if ($query->exists()) {
                        $fail("Path с full_path '{$value}' уже существует в этом Blueprint.");
                    }
                },
            ],
            'data_type' => [
                'required',
                'string',
                Rule::in(['string', 'int', 'float', 'bool', 'text', 'json', 'ref']),
            ],
            'cardinality' => [
                'required',
                'string',
                Rule::in(['one', 'many']),
            ],
            'is_indexed' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'ref_target_type' => [
                'nullable',
                'string',
                'max:100',
                // ref_target_type обязателен для data_type=ref
                function ($attribute, $value, $fail) {
                    if ($this->input('data_type') === 'ref' && !$value) {
                        $fail('ref_target_type обязателен для data_type=ref.');
                    }
                },
            ],
            'validation_rules' => ['nullable', 'array'],
            'ui_options' => ['nullable', 'array'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:paths,id',
                // Запрет parent_id в компонентах
                function ($attribute, $value, $fail) use ($blueprint) {
                    if ($blueprint && $blueprint->isComponent() && $value !== null) {
                        $fail('Вложенные Paths (parent_id) не поддерживаются в component Blueprint.');
                    }
                },
            ],
        ];
    }

    /**
     * Кастомные сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Имя поля должно начинаться с буквы или подчёркивания и содержать только буквы, цифры и подчёркивания.',
            'data_type.in' => 'Тип данных должен быть одним из: string, int, float, bool, text, json, ref.',
            'cardinality.in' => 'Cardinality должен быть либо "one", либо "many".',
        ];
    }
}

