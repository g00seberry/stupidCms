<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\PostType;
use App\Models\Taxonomy;
use App\Rules\TemplatePathRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Request для обновления типа записи (PostType).
 *
 * Валидирует данные для обновления типа записи:
 * - Все поля опциональны (sometimes)
 * - template: путь к шаблону (должен быть в папке templates)
 *
 * @package App\Http\Requests\Admin
 */
class UpdatePostTypeRequest extends FormRequest
{
    /**
     * Определить, авторизован ли пользователь для выполнения запроса.
     *
     * Авторизация обрабатывается middleware маршрута (can:manage.posttypes).
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
     * Валидирует (все поля опциональны):
     * - name: название (максимум 255 символов)
     * - template: путь к шаблону (должен быть в папке templates)
     * - options_json: обязательный объект (present, не массив)
     * - blueprint_id: опциональный ID Blueprint (nullable, должен существовать)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'template' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                new TemplatePathRule(app(\App\Domain\View\TemplatePathValidator::class)),
            ],
            'options_json' => [
                'present',
                'array',
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        $fail('The options_json field is required.');
                        return;
                    }

                    if (! is_array($value)) {
                        return;
                    }

                    if ($value === []) {
                        // Empty object is allowed and comes through as []
                        return;
                    }

                    if (array_is_list($value)) {
                        $fail('The options_json field must be an object.');
                    }
                },
            ],
            'blueprint_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('blueprints', 'id'),
            ],
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
            'template.string' => 'The template must be a string.',
            'template.max' => 'The template may not be greater than 255 characters.',
            'options_json.present' => 'The options_json field is required.',
            'options_json.array' => 'The options_json field must be an object.',
            'blueprint_id.integer' => 'The blueprint_id must be an integer.',
            'blueprint_id.exists' => 'The specified blueprint does not exist.',
        ];
    }

    /**
     * Настроить валидатор с дополнительной логикой.
     *
     * Валидирует taxonomies в options_json: массив целых чисел, существование таксономий.
     * Warnings не блокируют валидацию, только предупреждают.
     *
     * @param \Illuminate\Validation\Validator $validator Валидатор
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('options_json.taxonomies', [
            'array',
            function ($attribute, $value, $fail) {
                if (! is_array($value) || ! array_is_list($value)) {
                    $fail('The taxonomies must be an array.');
                    return;
                }

                foreach ($value as $item) {
                    if (! is_numeric($item) || (int) $item <= 0) {
                        $fail('The taxonomies must be an array of positive integers.');
                        return;
                    }
                }

                // Проверяем существование таксономий
                $taxonomyIds = array_map(fn ($item) => (int) $item, $value);
                $existingIds = Taxonomy::query()
                    ->whereIn('id', $taxonomyIds)
                    ->pluck('id')
                    ->toArray();

                $missingIds = array_diff($taxonomyIds, $existingIds);
                if (! empty($missingIds)) {
                    $fail('The following taxonomy IDs do not exist: ' . implode(', ', $missingIds) . '.');
                }
            },
        ], fn ($input) => isset($input['options_json']['taxonomies']));

        // Warnings не блокируют валидацию, только предупреждают
        // Реальная передача warnings происходит через метод warnings() в контроллере
    }

    /**
     * Получить warnings для добавления в meta ответа.
     *
     * @return array<string, array<string>> Массив warnings
     */
    public function warnings(): array
    {
        return [];
    }
}

