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
     * Убедиться, что для элементов массивов с data_type: 'json' добавлено правило 'array'.
     *
     * Это критично для правильной валидации вложенных полей внутри массивов объектов.
     *
     * @param array<string, array<int, string>> $laravelRules Правила валидации (изменяется по ссылке)
     * @param array<string, string> $dataTypes Маппинг путей на типы данных
     * @return void
     */
    private function ensureArrayRuleForJsonArrayElements(array &$laravelRules, array $dataTypes): void
    {
        foreach ($dataTypes as $field => $dataType) {
            // Для элементов массивов (заканчивающихся на .*) с data_type: 'json' или 'array'
            // В buildDataTypesMapping для json с cardinality: many устанавливается 'array'
            if (str_ends_with($field, '.*') && ($dataType === 'json' || $dataType === 'array')) {
                if (!isset($laravelRules[$field])) {
                    // Если правил нет, добавляем только 'array'
                    $laravelRules[$field] = ['array'];
                } elseif (!in_array('array', $laravelRules[$field], true)) {
                    // Если правил есть, но нет 'array', добавляем его в начало (после required/nullable)
                    $rules = $laravelRules[$field];
                    $insertPosition = 0;
                    foreach ($rules as $index => $rule) {
                        if (in_array($rule, ['required', 'nullable'], true)) {
                            $insertPosition = $index + 1;
                        } else {
                            break;
                        }
                    }
                    array_splice($rules, $insertPosition, 0, ['array']);
                    $laravelRules[$field] = $rules;
                }
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

        // ВАЖНО: Для элементов массивов с data_type: 'json' нужно явно добавить правило 'array'
        // ДО добавления правил для вложенных полей, чтобы Laravel правильно валидировал структуру
        $this->ensureArrayRuleForJsonArrayElements($laravelRules, $dataTypes);

        // Добавляем правила для вложенных полей content_json
        // Сначала добавляем правила для самих массивов (чтобы Laravel понимал структуру),
        // потом для элементов массивов (.*), потом для вложенных полей (.*.field)
        $arrayRules = [];
        $arrayElementRules = [];
        $nestedRules = [];
        foreach ($laravelRules as $field => $rules) {
            // Правила для самих массивов (например, content_json.authors)
            // Это поля с cardinality: 'many', которые не заканчиваются на .*
            if (!str_ends_with($field, '.*') && str_starts_with($field, 'content_json.')) {
                $arrayRules[$field] = $rules;
            } elseif (str_ends_with($field, '.*') && !str_contains($field, '.*.')) {
                // Правила для элементов массивов (например, content_json.author.*)
                $arrayElementRules[$field] = $rules;
            } else {
                // Правила для вложенных полей (например, content_json.author.*.name)
                $nestedRules[$field] = $rules;
            }
        }

        // Сначала добавляем правила для самих массивов (чтобы Laravel понимал структуру ДО обработки вложенных полей)
        foreach ($arrayRules as $field => $rules) {
            $validator->addRules([$field => $rules]);
        }

        // Потом добавляем правила для элементов массивов
        foreach ($arrayElementRules as $field => $rules) {
            $validator->addRules([$field => $rules]);
        }

        // Потом добавляем правила для вложенных полей
        foreach ($nestedRules as $field => $rules) {
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
     * Учитывает вложенные поля внутри массивов (заменяет сегменты на * для cardinality: 'many').
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

        // Создаём маппинг full_path → cardinality для определения родительских массивов
        $pathCardinalities = [];
        foreach ($paths as $path) {
            $pathCardinalities[$path->full_path] = $path->cardinality;
        }

        foreach ($paths as $path) {
            $fieldPath = $this->buildFieldPathForMapping($path->full_path, $pathCardinalities);

            // Для cardinality: 'many' добавляем маппинг для элементов массива
            // Для самого массива тип будет "array" (добавляется в addArrayRulesForManyFields)
            if ($path->cardinality === 'many') {
                // Если поле находится внутри массива объектов (путь содержит .*),
                // то для самого массива (например, articles.*.tags) тип должен быть "array",
                // а для элементов массива (articles.*.tags.*) - data_type поля
                if (str_contains($fieldPath, '.*')) {
                    // Поле внутри массива объектов: добавляем маппинг для самого массива как "array"
                    $dataTypes[$fieldPath] = 'array';
                    // И маппинг для элементов массива с исходным data_type
                    $dataTypes[$fieldPath.'.*'] = $path->data_type;
                } else {
                    // Поле на верхнем уровне
                    // Для data_type: 'json' элементы массива должны быть объектами (массивами),
                    // поэтому используем 'array' вместо 'json' для элементов
                    if ($path->data_type === 'json') {
                        $dataTypes[$fieldPath.'.*'] = 'array';
                    } else {
                        $dataTypes[$fieldPath.'.*'] = $path->data_type;
                    }
                }
            } else {
                // Для cardinality: 'one' добавляем маппинг для самого поля
                $dataTypes[$fieldPath] = $path->data_type;
            }
        }

        return $dataTypes;
    }

    /**
     * Построить путь поля в точечной нотации для маппинга типов данных.
     *
     * Преобразует full_path из Path в путь для content_json.
     * Если родительский путь имеет cardinality: 'many', заменяет соответствующий сегмент на '*'.
     *
     * @param string $fullPath Полный путь из Path (например, 'author.contacts.phone')
     * @param array<string, string> $pathCardinalities Маппинг full_path → cardinality для всех путей
     * @return string Путь в точечной нотации для валидации (например, 'content_json.author.contacts.phone' или 'content_json.author.*.contacts.phone')
     */
    private function buildFieldPathForMapping(string $fullPath, array $pathCardinalities): string
    {
        $segments = explode('.', $fullPath);
        $resultSegments = [];

        // Обрабатываем каждый сегмент пути
        for ($i = 0; $i < count($segments); $i++) {
            // Строим путь до текущего сегмента для проверки cardinality
            $parentPath = implode('.', array_slice($segments, 0, $i));
            
            // Если это не первый сегмент, проверяем cardinality родительского пути
            if ($i > 0 && isset($pathCardinalities[$parentPath]) && $pathCardinalities[$parentPath] === 'many') {
                // Родительский путь - массив, заменяем текущий сегмент на '*'
                // НО сохраняем имя сегмента для следующей итерации
                $resultSegments[] = '*';
                // Добавляем имя сегмента после '*'
                $resultSegments[] = $segments[$i];
            } else {
                // Обычный сегмент пути
                $resultSegments[] = $segments[$i];
            }
        }

        return 'content_json.'.implode('.', $resultSegments);
    }

    /**
     * Добавить правило "array" для полей с cardinality: 'many'.
     *
     * Правило "array" является Laravel-специфичным и добавляется здесь,
     * так как оно не имеет смысла в доменной модели.
     * Правило "array" должно быть перед правилами min/max для массивов
     * (так как min/max для массивов означают количество элементов).
     * Учитывает вложенные массивы (использует правильные пути с *).
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

        // Создаём маппинг full_path → cardinality для определения родительских массивов
        $pathCardinalities = [];
        foreach ($blueprint->paths()->select(['full_path', 'cardinality'])->get() as $path) {
            $pathCardinalities[$path->full_path] = $path->cardinality;
        }

        foreach ($paths as $path) {
            $fieldPath = $this->buildFieldPathForMapping($path->full_path, $pathCardinalities);

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
            } else {
                // Если правил нет, добавляем только 'array' для поля с cardinality: 'many'
                // Это важно, чтобы Laravel понимал, что это массив, а не объект
                $laravelRules[$fieldPath] = ['array'];
            }
        }
    }

}

