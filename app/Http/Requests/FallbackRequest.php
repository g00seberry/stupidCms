<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для fallback маршрута (404).
 *
 * Обрабатывает все запросы, которые не совпали с другими маршрутами.
 * Публичный запрос без валидации параметров.
 *
 * @package App\Http\Requests
 */
final class FallbackRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Публичный запрос, доступен всем.
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
     * @return array<string, mixed> Пустой массив (валидация не требуется)
     */
    public function rules(): array
    {
        return [];
    }
}
