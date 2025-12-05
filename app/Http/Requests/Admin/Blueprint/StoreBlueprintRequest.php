<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Blueprint;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request для создания Blueprint.
 *
 * Валидирует данные для создания blueprint:
 * - name: обязательное название (максимум 255 символов)
 * - code: обязательный уникальный код (regex: a-z0-9_)
 * - description: опциональное описание (максимум 1000 символов)
 *
 * @package App\Http\Requests\Admin\Blueprint
 */
class StoreBlueprintRequest extends FormRequest
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
     * - name: обязательное название (максимум 255 символов)
     * - code: обязательный уникальный код (regex: a-z0-9_)
     * - description: опциональное описание (максимум 1000 символов)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:blueprints,code', 'regex:/^[a-z0-9_]+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
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
            'code.regex' => 'Код может содержать только строчные буквы, цифры и подчёркивания.',
            'code.unique' => 'Blueprint с таким кодом уже существует.',
        ];
    }
}

