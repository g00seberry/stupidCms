<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Blueprint;

class StoreBlueprintRequest extends FormRequest
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
        $blueprintId = $this->route('blueprint')?->id;

        return [
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                // Кастомная валидация уникальности с учётом type и post_type_id
                function ($attribute, $value, $fail) use ($blueprintId) {
                    $type = $this->input('type', 'full');
                    $postTypeId = $this->input('post_type_id');

                    $query = Blueprint::where('slug', $value)
                        ->where('type', $type);

                    if ($type === 'full' && $postTypeId) {
                        $query->where('post_type_id', $postTypeId);
                    }

                    if ($blueprintId) {
                        $query->where('id', '!=', $blueprintId);
                    }

                    if ($query->exists()) {
                        if ($type === 'component') {
                            $fail('Component Blueprint с таким slug уже существует.');
                        } else {
                            $fail('Full Blueprint с таким slug уже существует для данного PostType.');
                        }
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in(['full', 'component'])],
            'is_default' => ['nullable', 'boolean'],
            'post_type_id' => [
                'nullable',
                'integer',
                'exists:post_types,id',
                // post_type_id обязателен для type=full
                function ($attribute, $value, $fail) {
                    if ($this->input('type') === 'full' && !$value) {
                        $fail('post_type_id обязателен для full Blueprint.');
                    }
                },
                // post_type_id должен быть null для type=component
                function ($attribute, $value, $fail) {
                    if ($this->input('type') === 'component' && $value !== null) {
                        $fail('post_type_id должен быть null для component Blueprint.');
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
            'slug.regex' => 'Slug может содержать только строчные буквы, цифры, дефисы и подчёркивания.',
            'type.in' => 'Тип должен быть либо "full", либо "component".',
        ];
    }
}

