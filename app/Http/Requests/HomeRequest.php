<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для главной страницы (/).
 *
 * Публичный запрос без валидации параметров.
 *
 * @package App\Http\Requests
 */
final class HomeRequest extends FormRequest
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


