<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapterInterface;
use App\Domain\Blueprint\Validation\EntryValidationServiceInterface;
use App\Models\Entry;
use App\Rules\Publishable;
use App\Rules\ReservedSlug;
use App\Rules\UniqueEntrySlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Request для обновления записи (Entry).
 *
 * Валидирует данные для обновления записи контента:
 * - Все поля опциональны (sometimes)
 * - Проверяет уникальность slug в рамках типа записи (исключая текущую запись)
 * - Проверяет зарезервированные пути
 *
 * @package App\Http\Requests\Admin
 */
class UpdateEntryRequest extends FormRequest
{
    /**
     * @var \App\Models\Entry|null Кэшированная модель записи
     */
    private ?Entry $entryModel = null;

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
     * Валидирует (все поля опциональны):
     * - title: заголовок (максимум 500 символов)
     * - slug: slug (regex, уникальность, зарезервированные пути)
     * - content_json: опциональный JSON массив (валидируется по правилам Blueprint, если привязан)
     * - meta_json: опциональный JSON массив
     * - is_published: boolean
     * - published_at: дата публикации
     * - template_override: шаблон
     * - term_ids: массив ID термов
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $entryId = $this->route('id');
        // Используем eager loading для избежания N+1 запросов
        $entry = Entry::query()
            ->withTrashed()
            ->with('postType.blueprint')
            ->find($entryId);
        $this->entryModel = $entry;
        
        if (! $entry) {
            return [];
        }

        $postType = $entry->postType;
        $postTypeSlug = $postType ? $postType->slug : 'page';

        return [
            'title' => 'sometimes|required|string|max:500',
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/',
                new UniqueEntrySlug($postTypeSlug, $entry->id),
                new ReservedSlug(),
                (new Publishable())->setData($this->all()),
            ],
            'content_json' => ['sometimes', 'nullable', 'array'],
            'meta_json' => 'sometimes|nullable|array',
            'is_published' => 'sometimes|boolean',
            'published_at' => 'sometimes|nullable|date',
            'template_override' => 'sometimes|nullable|string|max:255',
            'term_ids' => 'sometimes|nullable|array',
            'term_ids.*' => 'integer|exists:terms,id',
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
            'title.required' => 'The title field is required.',
            'title.max' => 'The title may not be greater than 500 characters.',
            'slug.required' => 'The slug field is required.',
            'slug.regex' => 'The slug format is invalid. Only lowercase letters, numbers, and hyphens are allowed.',
            'slug.max' => 'The slug may not be greater than 255 characters.',
            'published_at.date' => 'The published date must be a valid date.',
            'term_ids.array' => 'The term_ids field must be an array.',
            'term_ids.*.exists' => 'One or more specified terms do not exist.',
        ];
    }

    /**
     * Настроить валидатор с дополнительной логикой.
     *
     * Проверяет, что при публикации записи указан валидный slug
     * (либо в запросе, либо в существующей записи).
     * Добавляет динамические правила валидации для content_json из Blueprint.
     *
     * @param \Illuminate\Validation\Validator $validator Валидатор
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $entry = $this->entryModel;

        if (! $entry) {
            return;
        }

        // Добавляем правила валидации для content_json из Blueprint
        $this->addBlueprintValidationRules($validator, $entry);

        $validator->after(function (Validator $validator) use ($entry): void {
            if (! $this->boolean('is_published')) {
                return;
            }

            $slugProvided = $this->has('slug');
            $slugValue = $slugProvided ? (string) $this->input('slug') : (string) ($entry->slug ?? '');

            if (trim($slugValue) === '') {
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
     * @param \App\Models\Entry $entry Запись для получения blueprint
     * @return void
     */
    private function addBlueprintValidationRules(Validator $validator, Entry $entry): void
    {
        $postType = $entry->postType;

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

}

