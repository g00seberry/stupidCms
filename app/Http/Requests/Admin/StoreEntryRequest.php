<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapterInterface;
use App\Domain\Blueprint\Validation\EntryValidationServiceInterface;
use App\Models\PostType;
use App\Rules\Publishable;
use App\Rules\ReservedSlug;
use App\Rules\UniqueEntrySlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Request для создания новой записи (Entry).
 *
 * Валидирует данные для создания записи контента:
 * - Обязательные: post_type, title
 * - Опциональные: slug (автогенерация), content_json, meta_json, published_at
 * - Проверяет уникальность slug в рамках типа записи
 * - Проверяет зарезервированные пути
 *
 * @package App\Http\Requests\Admin
 */
class StoreEntryRequest extends FormRequest
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
        return true; // Authorization handled by route middleware
    }

    /**
     * Получить правила валидации для запроса.
     *
     * Валидирует:
     * - post_type: обязательный slug типа записи (должен существовать)
     * - title: обязательный заголовок (максимум 500 символов)
     * - slug: опциональный slug (regex, уникальность, зарезервированные пути)
     * - content_json: опциональный JSON массив (валидируется по правилам Blueprint, если привязан)
     * - meta_json: опциональный JSON массив
     * - is_published: опциональный boolean
     * - published_at: опциональная дата публикации
     * - template_override: опциональный шаблон
     * - term_ids: опциональный массив ID термов
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $postTypeSlug = $this->input('post_type', 'page');

        return [
            'post_type' => 'required|string|exists:post_types,slug',
            'title' => 'required|string|max:500',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/',
                new UniqueEntrySlug($postTypeSlug),
                new ReservedSlug(),
                (new Publishable())->setData($this->all()),
            ],
            'content_json' => ['nullable', 'array'],
            'meta_json' => 'nullable|array',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'template_override' => 'nullable|string|max:255',
            'term_ids' => 'nullable|array',
            'term_ids.*' => 'integer|exists:terms,id',
        ];
    }

    /**
     * Подготовить данные для валидации.
     *
     * Автоматически устанавливает published_at в текущее время,
     * если is_published=true и published_at не указан.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->boolean('is_published') && ! $this->has('published_at')) {
            $this->merge([
                'published_at' => now()->toDateTimeString(),
            ]);
        }
    }

    /**
     * Настроить валидатор с дополнительной логикой.
     *
     * Проверяет, что при публикации записи указан валидный slug.
     * Добавляет динамические правила валидации для content_json из Blueprint.
     *
     * @param \Illuminate\Validation\Validator $validator Валидатор
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        // Добавляем правила валидации для content_json из Blueprint
        $this->addBlueprintValidationRules($validator);

        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_published')) {
                return;
            }

            if ($this->has('slug') && trim((string) $this->input('slug')) === '') {
                $validator->errors()->add('slug', 'A valid slug is required when publishing an entry.');
            }
        });
    }

    /**
     * Добавить правила валидации для content_json из Blueprint.
     *
     * Использует доменный сервис EntryValidationService для построения RuleSet
     * и адаптер LaravelValidationAdapter для преобразования в Laravel правила.
     *
     * @param \Illuminate\Validation\Validator $validator Валидатор
     * @return void
     */
    private function addBlueprintValidationRules(Validator $validator): void
    {
        $postTypeSlug = $this->input('post_type');
        if (! $postTypeSlug) {
            return;
        }

        $postType = PostType::query()
            ->with('blueprint')
            ->where('slug', $postTypeSlug)
            ->first();

        if (! $postType || ! $postType->blueprint) {
            return;
        }

        // Используем доменный сервис для построения RuleSet
        $validationService = app(EntryValidationServiceInterface::class);
        $ruleSet = $validationService->buildRulesFor($postType->blueprint);

        if ($ruleSet->isEmpty()) {
            return;
        }

        // Собираем маппинг dataTypes для адаптера
        $dataTypes = $this->buildDataTypesMapping($postType->blueprint);

        // Адаптируем RuleSet в Laravel правила
        $adapter = app(LaravelValidationAdapterInterface::class);
        $laravelRules = $adapter->adapt($ruleSet, $dataTypes);

        // Добавляем правило "array" для полей с cardinality: 'many'
        $this->addArrayRulesForManyFields($laravelRules, $postType->blueprint);

        // Добавляем правила для вложенных полей content_json
        foreach ($laravelRules as $field => $rules) {
            $validator->addRules([$field => $rules]);
        }
    }

    /**
     * Построить маппинг путей полей на типы данных для адаптера.
     *
     * Создаёт массив, где ключи - пути полей в точечной нотации (content_json.*),
     * значения - типы данных Path (string, int, float и т.д.).
     * Для полей с cardinality: 'many' базовый тип для самого массива будет "array"
     * (добавляется в addArrayRulesForManyFields), а здесь указывается тип для элементов.
     *
     * @param \App\Models\Blueprint $blueprint Blueprint для построения маппинга
     * @return array<string, string> Маппинг путей на типы данных
     */
    private function buildDataTypesMapping(\App\Models\Blueprint $blueprint): array
    {
        $dataTypes = [];

        $paths = $blueprint->paths()
            ->select(['full_path', 'data_type', 'cardinality'])
            ->get();

        foreach ($paths as $path) {
            $fieldPath = 'content_json.'.$path->full_path;

            // Для cardinality: 'many' добавляем маппинг для элементов массива
            // Для самого массива тип будет "array" (добавляется в addArrayRulesForManyFields)
            if ($path->cardinality === 'many') {
                $dataTypes[$fieldPath.'.*'] = $path->data_type;
                // Не добавляем маппинг для самого массива, так как тип "array" будет добавлен отдельно
            } else {
                // Для cardinality: 'one' добавляем маппинг для самого поля
                $dataTypes[$fieldPath] = $path->data_type;
            }
        }

        return $dataTypes;
    }

    /**
     * Добавить правило "array" для полей с cardinality: 'many'.
     *
     * Правило "array" является Laravel-специфичным и добавляется здесь,
     * так как оно не имеет смысла в доменной модели.
     * Правило "array" должно быть перед правилами min/max для массивов
     * (так как min/max для массивов означают количество элементов).
     *
     * @param array<string, array<int, string>> $laravelRules Правила валидации (изменяется по ссылке)
     * @param \App\Models\Blueprint $blueprint Blueprint для определения cardinality
     * @return void
     */
    private function addArrayRulesForManyFields(array &$laravelRules, \App\Models\Blueprint $blueprint): void
    {
        $paths = $blueprint->paths()
            ->select(['full_path', 'cardinality'])
            ->where('cardinality', 'many')
            ->get();

        foreach ($paths as $path) {
            $fieldPath = 'content_json.'.$path->full_path;

            if (isset($laravelRules[$fieldPath])) {
                // Вставляем "array" после required/nullable, но перед остальными правилами (включая min/max для массивов)
                $rules = $laravelRules[$fieldPath];
                $insertPosition = 0;
                foreach ($rules as $index => $rule) {
                    if (in_array($rule, ['required', 'nullable'], true)) {
                        $insertPosition = $index + 1;
                    } else {
                        break;
                    }
                }
                array_splice($rules, $insertPosition, 0, ['array']);
                $laravelRules[$fieldPath] = $rules;
            }
        }
    }

    /**
     * Получить кастомные сообщения для ошибок валидации.
     *
     * @return array<string, string> Массив сообщений об ошибках
     */
    public function messages(): array
    {
        return [
            'post_type.required' => 'The post type field is required.',
            'post_type.exists' => 'The specified post type does not exist.',
            'title.required' => 'The title field is required.',
            'title.max' => 'The title may not be greater than 500 characters.',
            'slug.regex' => 'The slug format is invalid. Only lowercase letters, numbers, and hyphens are allowed.',
            'slug.max' => 'The slug may not be greater than 255 characters.',
            'published_at.date' => 'The published date must be a valid date.',
            'term_ids.array' => 'The term_ids field must be an array.',
            'term_ids.*.exists' => 'One or more specified terms do not exist.',
        ];
    }

}

