<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\FormPreset;

use App\Rules\FormConfigBlueprintRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Request для сохранения/обновления конфигурации формы компонентов (PUT).
 *
 * Валидирует данные для создания или обновления конфигурации формы:
 * - config_json: обязательный объект (не массив), где ключи - full_path из Path, значения - EditComponent
 * - Ключи (пути к узлам) должны быть непустыми строками
 * - Значения (EditComponent) должны быть объектами с обязательными полями name и props
 *
 * @package App\Http\Requests\Admin\FormPreset
 */
class StoreFormConfigRequest extends FormRequest
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
     * - config_json: обязательный объект (не массив)
     * - Ключи объекта должны быть непустыми строками
     * - Значения должны быть объектами (EditComponent)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'config_json' => [
                'required',
                'array',
                function ($attribute, $value, $fail) {
                    if ($value === []) {
                        return; // Пустой объект допустим
                    }

                    if (array_is_list($value)) {
                        $fail('The config_json field must be an object, not an array.');
                        return;
                    }

                    // Проверяем, что все ключи - непустые строки
                    foreach (array_keys($value) as $key) {
                        if (! is_string($key) || $key === '') {
                            $fail('All keys in config_json must be non-empty strings (full_path from Path).');
                            return;
                        }
                    }

                    // Проверяем, что все значения - объекты (EditComponent)
                    foreach ($value as $path => $component) {
                        if (! is_array($component) || array_is_list($component)) {
                            $fail("The value for path '{$path}' must be an object (EditComponent).");
                            return;
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Настроить валидатор с дополнительной логикой.
     *
     * Валидирует структуру EditComponent для каждого пути (обязательные поля: name, props).
     * Валидирует соответствие схеме blueprint через FormConfigBlueprintRule.
     *
     * @param \Illuminate\Validation\Validator $validator Валидатор
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        // Валидация соответствия схеме blueprint
        $blueprint = $this->route('blueprint');
        if ($blueprint instanceof \App\Models\Blueprint) {
            $validator->sometimes(
                'config_json',
                new FormConfigBlueprintRule($blueprint->id),
                fn () => true
            );
        }

        // Валидация структуры EditComponent для каждого пути
        $validator->after(function (Validator $validator) {
            $config = $this->input('config_json', []);

            if (! is_array($config) || array_is_list($config)) {
                return;
            }

            foreach ($config as $path => $component) {
                if (! is_array($component) || array_is_list($component)) {
                    continue;
                }

                // Проверяем обязательное поле name
                if (! isset($component['name']) || ! is_string($component['name']) || $component['name'] === '') {
                    $validator->errors()->add(
                        "config_json.{$path}.name",
                        "The 'name' field is required and must be a non-empty string for path '{$path}'."
                    );
                }
                
                // Проверяем обязательное поле props (должно быть объектом)
                if (! isset($component['props'])) {
                    $validator->errors()->add(
                        "config_json.{$path}.props",
                        "The 'props' field is required and must be an object for path '{$path}'."
                    );
                } elseif (! is_array($component['props']) || array_is_list($component['props'])) {
                    $validator->errors()->add(
                        "config_json.{$path}.props",
                        "The 'props' field must be an object for path '{$path}'."
                    );
                }
            }
        });
    }

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function messages(): array
    {
        return [
            'config_json.required' => 'The config_json field is required.',
            'config_json.array' => 'The config_json field must be an object.',
        ];
    }
}
