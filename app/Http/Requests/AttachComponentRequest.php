<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Blueprint;

class AttachComponentRequest extends FormRequest
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

        return [
            'component_id' => [
                'required',
                'integer',
                'exists:blueprints,id',
                // Только type=component
                function ($attribute, $value, $fail) {
                    $component = Blueprint::find($value);
                    if ($component && !$component->isComponent()) {
                        $fail('Можно прикрепить только component Blueprint.');
                    }
                },
                // Запрет self
                function ($attribute, $value, $fail) use ($blueprint) {
                    if ($blueprint && $blueprint->id === (int) $value) {
                        $fail('Нельзя прикрепить Blueprint сам к себе.');
                    }
                },
                // Проверка циклов (упрощённая)
                function ($attribute, $value, $fail) use ($blueprint) {
                    if (!$blueprint) {
                        return;
                    }

                    $component = Blueprint::find($value);
                    if (!$component) {
                        return;
                    }

                    // Проверяем, не использует ли component уже наш blueprint
                    if ($component->components()->where('component_id', $blueprint->id)->exists()) {
                        $fail('Обнаружен цикл: компонент уже использует данный Blueprint.');
                    }
                },
            ],
            'path_prefix' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/',
                // Проверка конфликтов Path
                function ($attribute, $value, $fail) use ($blueprint) {
                    if (!$blueprint) {
                        return;
                    }

                    $componentId = $this->input('component_id');
                    $component = Blueprint::find($componentId);

                    if (!$component) {
                        return;
                    }

                    // Проверяем конфликты full_path
                    $existingPaths = $blueprint->paths()->pluck('full_path');

                    foreach ($component->ownPaths as $sourcePath) {
                        $newFullPath = $value . '.' . $sourcePath->full_path;

                        if ($existingPaths->contains($newFullPath)) {
                            $fail("Конфликт: Path '{$newFullPath}' уже существует в Blueprint.");
                            return;
                        }
                    }
                },
                // Проверка уникальности path_prefix
                function ($attribute, $value, $fail) use ($blueprint) {
                    if (!$blueprint) {
                        return;
                    }

                    // Проверяем, что path_prefix не конфликтует с существующими
                    $existingPrefixes = $blueprint->components()
                        ->get()
                        ->pluck('pivot.path_prefix');

                    if ($existingPrefixes->contains($value)) {
                        $fail("path_prefix '{$value}' уже используется в этом Blueprint.");
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
            'path_prefix.regex' => 'path_prefix должен начинаться с буквы или подчёркивания и содержать только буквы, цифры и подчёркивания.',
        ];
    }
}

