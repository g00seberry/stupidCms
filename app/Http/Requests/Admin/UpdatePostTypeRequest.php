<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Taxonomy;
use App\Rules\ReservedSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Request для обновления типа записи (PostType).
 *
 * Валидирует данные для обновления типа записи:
 * - Все поля опциональны (sometimes)
 * - Проверяет уникальность slug (исключая текущий тип)
 * - Проверяет зарезервированные пути
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
     * - slug: slug (regex, уникальность, зарезервированные пути)
     * - name: название (максимум 255 символов)
     * - options_json: обязательный объект (present, не массив)
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $slug = $this->route('slug');

        return [
            'slug' => [
                'sometimes',
                'string',
                'max:64',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('post_types', 'slug')->ignore($slug, 'slug'),
                new ReservedSlug(),
            ],
            'name' => [
                'sometimes',
                'string',
                'max:255',
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
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, underscores, and hyphens.',
            'options_json.present' => 'The options_json field is required.',
            'options_json.array' => 'The options_json field must be an object.',
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

