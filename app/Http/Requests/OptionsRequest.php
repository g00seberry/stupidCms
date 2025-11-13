<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Entry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request для работы с опциями (Options).
 *
 * Валидирует namespace, key и value для опций.
 * Проверяет allow-list из конфига и специальную валидацию
 * для site:home_entry_id (проверка существования записи).
 *
 * @package App\Http\Requests
 */
class OptionsRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Авторизация должна быть настроена через middleware.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Авторизация должна быть настроена через middleware
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - namespace: обязательный строковый namespace
     * - key: обязательный строковый ключ
     * - value: опциональное значение
     *
     * Для site:home_entry_id дополнительно проверяет существование записи.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $namespace = $this->input('namespace');
        $key = $this->input('key');

        // Проверка allow-list
        $allowed = config('options.allowed', []);
        if (!isset($allowed[$namespace]) || !in_array($key, $allowed[$namespace], true)) {
            return [
                'namespace' => ['required', 'string'],
                'key' => ['required', 'string'],
                'value' => ['nullable'],
            ];
        }

        // Специальная валидация для site:home_entry_id
        if ($namespace === 'site' && $key === 'home_entry_id') {
            return [
                'namespace' => 'required|string',
                'key' => 'required|string',
                'value' => [
                    'nullable',
                    'integer',
                    'min:1',
                    function ($attribute, $val, $fail) {
                        if (!is_null($val)) {
                            if (!is_int($val) && !ctype_digit((string) $val)) {
                                return; // Laravel уже проверит integer
                            }
                            $intVal = (int) $val;
                            if ($intVal < 1) {
                                return; // Laravel уже проверит min:1
                            }
                            if (!Entry::query()->whereKey($intVal)->exists()) {
                                $fail(__('validation.entry_not_found', [], 'ru'));
                            }
                        }
                    },
                ],
            ];
        }

        // Для других опций - базовая валидация
        return [
            'namespace' => 'required|string',
            'key' => 'required|string',
            'value' => 'nullable',
        ];
    }
}

