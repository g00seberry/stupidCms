<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\BlueprintEmbed;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для создания BlueprintEmbed.
 *
 * Валидирует данные для создания встраивания blueprint:
 * - embedded_blueprint_id: обязательный ID встраиваемого blueprint
 * - host_path_id: опциональный ID поля-контейнера (NULL = корень)
 *
 * @package App\Http\Requests\Admin\BlueprintEmbed
 */
class StoreEmbedRequest extends FormRequest
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
     * - embedded_blueprint_id: обязательный ID встраиваемого blueprint (существующий)
     * - host_path_id: опциональный ID поля-контейнера (существующий path)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'embedded_blueprint_id' => ['required', 'integer', 'exists:blueprints,id'],
            'host_path_id' => ['nullable', 'integer', 'exists:paths,id'],
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
            'embedded_blueprint_id.required' => 'Укажите Blueprint для встраивания.',
            'embedded_blueprint_id.exists' => 'Указанный Blueprint не найден.',
            'host_path_id.exists' => 'Указанный Path не найден.',
        ];
    }
}

