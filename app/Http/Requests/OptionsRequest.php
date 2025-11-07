<?php

namespace App\Http\Requests;

use App\Models\Entry;
use Illuminate\Foundation\Http\FormRequest;

class OptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Авторизация должна быть настроена через middleware
    }

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

