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
     * Подготовить данные для валидации.
     */
    protected function prepareForValidation(): void
    {
        $blueprint = $this->route('blueprint');
        
        if ($blueprint && !$this->has('blueprint_id')) {
            $this->merge([
                'blueprint_id' => $blueprint->id,
            ]);
        }
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
                'sometimes',
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
                Rule::in(['string', 'int', 'float', 'bool', 'text', 'json', 'ref', 'blueprint']),
            ],
            'cardinality' => [
                'required',
                'string',
                Rule::in(['one', 'many']),
            ],
            'is_indexed' => [
                'nullable',
                'boolean',
                // Для data_type=blueprint запрещаем is_indexed=true
                function ($attribute, $value, $fail) {
                    if ($this->input('data_type') === 'blueprint' && $value === true) {
                        $fail('Поля с data_type=blueprint не могут быть индексируемыми (is_indexed должен быть false).');
                    }
                },
            ],
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
            'embedded_blueprint_id' => [
                'nullable',
                'integer',
                'required_if:data_type,blueprint',
                'exists:blueprints,id',
                function ($attribute, $value, $fail) use ($blueprint) {
                    $dataType = $this->input('data_type');
                    
                    if ($dataType === 'blueprint' && $value) {
                        $embeddedBlueprint = Blueprint::find($value);

                        // Проверить, что это component
                        if ($embeddedBlueprint && !$embeddedBlueprint->isComponent()) {
                            $fail('embedded_blueprint_id должен указывать на Blueprint с type=component.');
                        }

                        // Защита от циклических ссылок
                        if ($blueprint && $value == $blueprint->id) {
                            $fail('Нельзя встроить Blueprint сам в себя.');
                        }
                    } elseif ($value && $dataType !== 'blueprint') {
                        // Для других типов данных embedded_blueprint_id должен быть null
                        $fail('embedded_blueprint_id может быть указан только для data_type=blueprint.');
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
            'data_type.in' => 'Тип данных должен быть одним из: string, int, float, bool, text, json, ref, blueprint.',
            'cardinality.in' => 'Cardinality должен быть либо "one", либо "many".',
        ];
    }
}

