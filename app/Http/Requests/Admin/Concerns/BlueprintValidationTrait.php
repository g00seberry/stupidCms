<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Concerns;

use App\Domain\Blueprint\Validation\Adapters\LaravelValidationAdapterInterface;
use App\Domain\Blueprint\Validation\DataTypeMapper;
use App\Domain\Blueprint\Validation\EntryValidationServiceInterface;
use App\Domain\Blueprint\Validation\FieldPathBuilder;
use App\Domain\Blueprint\Validation\RuleArrayManipulator;
use App\Domain\Blueprint\Validation\ValidationConstants;
use App\Models\Blueprint;
use App\Models\PostType;
use Illuminate\Validation\Validator;

/**
 * Trait для добавления валидации Blueprint в Request классы.
 *
 * @package App\Http\Requests\Admin\Concerns
 */
trait BlueprintValidationTrait
{
    /**
     * Добавить правила валидации для content_json из Blueprint.
     *
     * Использует доменный сервис EntryValidationService для построения RuleSet
     * и адаптер LaravelValidationAdapter для преобразования в Laravel правила.
     *
     * @param \Illuminate\Validation\Validator $validator Валидатор
     * @param \App\Models\PostType|null $postType PostType для получения blueprint (если null, будет получен из запроса)
     * @return void
     */
    protected function addBlueprintValidationRules(Validator $validator, ?PostType $postType = null): void
    {
        // Получаем PostType, если не передан
        if ($postType === null) {
            $postTypeSlug = $this->input('post_type');
            if (! $postTypeSlug) {
                return;
            }

            $postType = PostType::query()
                ->with('blueprint')
                ->where('slug', $postTypeSlug)
                ->first();
        }

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
        // LaravelValidationAdapter уже добавляет правило 'array' для элементов массивов с data_type: 'json'
        // в методе processArrayElementFields, который вызывается ДО обработки вложенных полей
        $adapter = app(LaravelValidationAdapterInterface::class);
        $laravelRules = $adapter->adapt($ruleSet, $dataTypes);

        // Добавляем правило "array" для полей с cardinality: 'many'
        $this->addArrayRulesForManyFields($laravelRules, $postType->blueprint);

        // ВАЖНО: Для элементов массивов с data_type: 'json' нужно явно добавить правило 'array'
        // ДО добавления правил для вложенных полей, чтобы Laravel правильно валидировал структуру
        // Это критично для валидации вложенных полей внутри массивов объектов
        // Дополнительно проверяем, что правило 'array' присутствует для всех элементов массивов
        $this->ensureArrayRuleForJsonArrayElements($laravelRules, $dataTypes);

        // Добавляем правила для вложенных полей content_json
        // Сначала добавляем правила для самих массивов (чтобы Laravel понимал структуру),
        // потом для элементов массивов (.*), потом для вложенных полей (.*.field)
        $this->addRulesInCorrectOrder($validator, $laravelRules);
    }

    /**
     * Добавить правила в правильном порядке.
     *
     * @param \Illuminate\Validation\Validator $validator Валидатор
     * @param array<string, array<int, string>> $laravelRules Правила валидации
     * @return void
     */
    private function addRulesInCorrectOrder(Validator $validator, array $laravelRules): void
    {
        $arrayRules = [];
        $arrayElementRules = [];
        $nestedRules = [];

        foreach ($laravelRules as $field => $rules) {
            // Правила для самих массивов (например, content_json.authors)
            if (! str_contains($field, ValidationConstants::ARRAY_ELEMENT_WILDCARD) && str_starts_with($field, ValidationConstants::CONTENT_JSON_PREFIX)) {
                $arrayRules[$field] = $rules;
            } elseif (str_ends_with($field, ValidationConstants::ARRAY_ELEMENT_WILDCARD)) {
                // Правила для элементов массивов (например, content_json.author.*)
                $arrayElementRules[$field] = $rules;
            } else {
                // Правила для вложенных полей (например, content_json.author.*.name)
                $nestedRules[$field] = $rules;
            }
        }

        // ВАЖНО: Порядок добавления правил критичен для правильной валидации вложенных полей
        // Сначала добавляем правила для самих массивов (чтобы Laravel понимал структуру)
        foreach ($arrayRules as $field => $rules) {
            $validator->addRules([$field => $rules]);
        }

        // Потом добавляем правила для элементов массивов (.*)
        // Это должно быть ДО правил для вложенных полей (.*.field), чтобы Laravel понимал,
        // что элементы массива - это объекты (массивы), и мог применять правила для вложенных полей
        foreach ($arrayElementRules as $field => $rules) {
            $validator->addRules([$field => $rules]);
        }

        // Потом добавляем правила для вложенных полей (.*.field)
        // Эти правила применяются к полям внутри объектов массива
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
    private function buildDataTypesMapping(Blueprint $blueprint): array
    {
        $dataTypes = [];
        $fieldPathBuilder = new FieldPathBuilder();
        $dataTypeMapper = new DataTypeMapper();

        $paths = $blueprint->paths()
            ->select(['full_path', 'data_type', 'cardinality'])
            ->get();

        // Создаём маппинг full_path → cardinality для определения родительских массивов
        $pathCardinalities = [];
        foreach ($paths as $path) {
            $pathCardinalities[$path->full_path] = $path->cardinality;
        }

        foreach ($paths as $path) {
            $fieldPath = $fieldPathBuilder->buildFieldPath($path->full_path, $pathCardinalities);

            // Для cardinality: 'many' добавляем маппинг для элементов массива
            if ($path->cardinality === ValidationConstants::CARDINALITY_MANY) {
                // Если поле находится внутри массива объектов (путь содержит .*),
                // то для самого массива (например, articles.*.tags) тип должен быть "array",
                // а для элементов массива (articles.*.tags.*) - data_type поля
                if (str_contains($fieldPath, ValidationConstants::ARRAY_ELEMENT_WILDCARD)) {
                    // Поле внутри массива объектов: добавляем маппинг для самого массива как "array"
                    $dataTypes[$fieldPath] = ValidationConstants::DATA_TYPE_ARRAY;
                    // И маппинг для элементов массива с исходным data_type
                    $dataTypes[$fieldPath.ValidationConstants::ARRAY_ELEMENT_WILDCARD] = $path->data_type;
                } else {
                    // Поле на верхнем уровне
                    // Для data_type: 'json' элементы массива должны быть объектами (массивами),
                    // поэтому используем 'array' вместо 'json' для элементов
                    if ($dataTypeMapper->isJsonType($path->data_type)) {
                        $dataTypes[$fieldPath.ValidationConstants::ARRAY_ELEMENT_WILDCARD] = ValidationConstants::DATA_TYPE_ARRAY;
                    } else {
                        $dataTypes[$fieldPath.ValidationConstants::ARRAY_ELEMENT_WILDCARD] = $path->data_type;
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
    private function addArrayRulesForManyFields(array &$laravelRules, Blueprint $blueprint): void
    {
        $fieldPathBuilder = new FieldPathBuilder();
        $ruleArrayManipulator = new RuleArrayManipulator();

        $paths = $blueprint->paths()
            ->select(['full_path', 'cardinality'])
            ->where('cardinality', ValidationConstants::CARDINALITY_MANY)
            ->get();

        // Создаём маппинг full_path → cardinality для определения родительских массивов
        $pathCardinalities = [];
        foreach ($blueprint->paths()->select(['full_path', 'cardinality'])->get() as $path) {
            $pathCardinalities[$path->full_path] = $path->cardinality;
        }

        foreach ($paths as $path) {
            $fieldPath = $fieldPathBuilder->buildFieldPath($path->full_path, $pathCardinalities);

            if (isset($laravelRules[$fieldPath])) {
                // Вставляем "array" после required/nullable, но перед остальными правилами
                $ruleArrayManipulator->ensureArrayRule($laravelRules[$fieldPath]);
            } else {
                // Если правил нет, добавляем только 'array' для поля с cardinality: 'many'
                $laravelRules[$fieldPath] = [ValidationConstants::RULE_ARRAY];
            }
        }
    }

    /**
     * Убедиться, что для элементов массивов с data_type: 'json' добавлено правило 'array'.
     *
     * Это критично для правильной валидации вложенных полей внутри массивов объектов.
     * Правило 'array' должно быть добавлено для элементов массивов (заканчивающихся на .*),
     * чтобы Laravel понимал структуру и мог применять правила для вложенных полей.
     *
     * @param array<string, array<int, string>> $laravelRules Правила валидации (изменяется по ссылке)
     * @param array<string, string> $dataTypes Маппинг путей на типы данных
     * @return void
     */
    private function ensureArrayRuleForJsonArrayElements(array &$laravelRules, array $dataTypes): void
    {
        $dataTypeMapper = new DataTypeMapper();
        $ruleArrayManipulator = new RuleArrayManipulator();

        foreach ($dataTypes as $field => $dataType) {
            // Для элементов массивов (заканчивающихся на .*) с data_type: 'json' или 'array'
            // ВАЖНО: Это должно быть добавлено ДО обработки вложенных полей (.*.field),
            // чтобы Laravel понимал структуру массива объектов
            if (str_ends_with($field, ValidationConstants::ARRAY_ELEMENT_WILDCARD) && $dataTypeMapper->isArrayType($dataType)) {
                if (! isset($laravelRules[$field])) {
                    // Если правил нет, добавляем только 'array'
                    // Это критично для валидации вложенных полей внутри массивов объектов
                    $laravelRules[$field] = [ValidationConstants::RULE_ARRAY];
                } else {
                    // Если правил есть, но нет 'array', добавляем его
                    $ruleArrayManipulator->ensureArrayRule($laravelRules[$field]);
                }
            }
        }
    }
}

