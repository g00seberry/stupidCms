<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Adapters;

use App\Domain\Blueprint\Validation\DataTypeMapper;
use App\Domain\Blueprint\Validation\RuleArrayManipulator;
use App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerRegistry;
use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Domain\Blueprint\Validation\Rules\RuleSet;
use App\Domain\Blueprint\Validation\ValidationConstants;
use App\Rules\JsonObject;

/**
 * Адаптер для преобразования доменных RuleSet в Laravel правила валидации.
 *
 * Преобразует доменные Rule объекты в строки правил валидации Laravel
 * через систему handlers.
 *
 * @package App\Domain\Blueprint\Validation\Adapters
 */
final class LaravelValidationAdapter implements LaravelValidationAdapterInterface
{
    /**
     * @param \App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerRegistry $registry Реестр обработчиков правил
     * @param \App\Domain\Blueprint\Validation\DataTypeMapper $dataTypeMapper Маппер типов данных
     * @param \App\Domain\Blueprint\Validation\RuleArrayManipulator $ruleArrayManipulator Манипулятор массивов правил
     */
    public function __construct(
        private readonly RuleHandlerRegistry $registry,
        private readonly DataTypeMapper $dataTypeMapper,
        private readonly RuleArrayManipulator $ruleArrayManipulator
    ) {}
    /**
     * Преобразовать RuleSet в массив правил Laravel.
     *
     * Преобразует доменные Rule объекты в строки правил валидации Laravel
     * (например, 'required', 'string', 'min:1', 'max:500', 'regex:/pattern/').
     * Также добавляет базовые типы данных (string, integer, numeric, boolean, date, array)
     * на основе dataTypes.
     *
     * @param \App\Domain\Blueprint\Validation\Rules\RuleSet $ruleSet Набор доменных правил
     * @param array<string, string> $dataTypes Маппинг путей полей на типы данных
     *         (например, ['content_json.title' => 'string', 'content_json.count' => 'int'])
     * @return array<string, array<int, string>> Массив правил валидации Laravel,
     *         где ключи - пути полей, значения - массивы строк правил
     */
    public function adapt(RuleSet $ruleSet, array $dataTypes = []): array
    {
        $laravelRules = [];

        // Сначала обрабатываем все поля, заканчивающиеся на .* с data_type: 'json' или 'array',
        // чтобы добавить правило 'array' ДО обработки вложенных полей
        $this->processArrayElementFields($laravelRules, $dataTypes);

        foreach ($ruleSet->getAllRules() as $field => $rules) {
            $fieldRules = [];

            // Преобразуем каждое правило в строку Laravel через handlers
            foreach ($rules as $rule) {
                $ruleType = $rule->getType();
                $handler = $this->registry->getHandler($ruleType);

                if ($handler === null) {
                    throw new \InvalidArgumentException("No handler found for rule type: {$ruleType}");
                }

                // Получаем dataType для поля (если указан)
                $dataType = $dataTypes[$field] ?? 'string';

                // Обрабатываем правило через handler
                $laravelRuleStrings = $handler->handle($rule, $dataType);
                $fieldRules = array_merge($fieldRules, $laravelRuleStrings);
            }

            // Добавляем базовый тип данных, если указан
            if (isset($dataTypes[$field])) {
                $this->addBaseTypeForField($fieldRules, $field, $dataTypes[$field]);
            }

            if (! empty($fieldRules)) {
                $this->mergeFieldRules($laravelRules, $field, $fieldRules);
            }
        }

        // Добавляем базовый тип для полей из dataTypes, которые не имеют правил в ruleSet
        $this->addBaseTypesForFieldsWithoutRules($laravelRules, $dataTypes);

        return $laravelRules;
    }


    /**
     * Обработать поля элементов массивов с data_type: 'json' или 'array'.
     *
     * @param array<string, array<int, string>> $laravelRules Правила валидации (изменяется по ссылке)
     * @param array<string, string> $dataTypes Маппинг путей на типы данных
     * @return void
     */
    private function processArrayElementFields(array &$laravelRules, array $dataTypes): void
    {
        foreach ($dataTypes as $field => $dataType) {
            if (str_ends_with($field, ValidationConstants::ARRAY_ELEMENT_WILDCARD) && $this->dataTypeMapper->isArrayType($dataType)) {
                if (! isset($laravelRules[$field])) {
                    $laravelRules[$field] = [ValidationConstants::RULE_ARRAY];
                } else {
                    $this->ruleArrayManipulator->ensureArrayRule($laravelRules[$field]);
                }
            }
        }
    }

    /**
     * Добавить базовый тип для поля.
     *
     * @param array<int, string|object> $fieldRules Правила поля (изменяется по ссылке)
     * @param string $field Путь поля
     * @param string $dataType Тип данных
     * @return void
     */
    private function addBaseTypeForField(array &$fieldRules, string $field, string $dataType): void
    {
        // Специальная обработка для типа 'array' (для массивов внутри массивов объектов)
        if ($dataType === ValidationConstants::DATA_TYPE_ARRAY) {
            $this->ruleArrayManipulator->ensureArrayRule($fieldRules);
            return;
        }

        $baseType = $this->dataTypeMapper->toLaravelRule($dataType);
        if ($baseType === null) {
            return;
        }

        // Для элементов массивов (заканчивающихся на .*) с data_type: 'json' или 'array'
        // правило 'array' уже добавлено в начале метода
        if (str_ends_with($field, ValidationConstants::ARRAY_ELEMENT_WILDCARD) && $this->dataTypeMapper->isArrayType($dataType)) {
            $this->ruleArrayManipulator->ensureArrayRule($fieldRules);
        } elseif ($this->dataTypeMapper->isJsonType($dataType) && ! str_ends_with($field, ValidationConstants::ARRAY_ELEMENT_WILDCARD)) {
            // Для json типа с cardinality: 'one' добавляем правило 'array' и проверку на объект
            $this->ruleArrayManipulator->ensureArrayRule($fieldRules);
            // Добавляем проверку, что это объект, а не массив
            if (! $this->hasJsonObjectRule($fieldRules)) {
                $fieldRules[] = new JsonObject();
            }
        } else {
            // Вставляем базовый тип после required/nullable, но перед остальными правилами
            $this->ruleArrayManipulator->insertAfterRequired($fieldRules, $baseType);
        }
    }

    /**
     * Объединить правила для поля.
     *
     * @param array<string, array<int, string|object>> $laravelRules Правила валидации (изменяется по ссылке)
     * @param string $field Путь поля
     * @param array<int, string|object> $fieldRules Новые правила для поля
     * @return void
     */
    private function mergeFieldRules(array &$laravelRules, string $field, array $fieldRules): void
    {
        if (! isset($laravelRules[$field])) {
            $laravelRules[$field] = $fieldRules;
            return;
        }

        // Объединяем правила, сохраняя порядок
        $merged = $this->ruleArrayManipulator->mergeRules($laravelRules[$field], $fieldRules);
        
        // Убеждаемся, что 'array' в правильной позиции (после required/nullable)
        $this->ensureArrayRulePosition($merged);
        
        $laravelRules[$field] = $merged;
    }

    /**
     * Убедиться, что правило 'array' в правильной позиции.
     *
     * @param array<int, string|object> $rules Правила (изменяется по ссылке)
     * @return void
     */
    private function ensureArrayRulePosition(array &$rules): void
    {
        $arrayIndex = array_search(ValidationConstants::RULE_ARRAY, $rules, true);
        if ($arrayIndex === false || $arrayIndex === 0) {
            return;
        }

        // Проверяем, что перед 'array' только required/nullable
        $hasNonRequiredBefore = false;
        foreach (array_slice($rules, 0, $arrayIndex) as $rule) {
            if (! is_string($rule) || ! in_array($rule, ValidationConstants::getRequiredNullableRules(), true)) {
                $hasNonRequiredBefore = true;
                break;
            }
        }

        if ($hasNonRequiredBefore) {
            // Перемещаем 'array' в правильную позицию
            unset($rules[$arrayIndex]);
            $rules = array_values($rules);
            $this->ruleArrayManipulator->ensureArrayRule($rules);
        }
    }

    /**
     * Добавить базовые типы для полей без правил.
     *
     * @param array<string, array<int, string|object>> $laravelRules Правила валидации (изменяется по ссылке)
     * @param array<string, string> $dataTypes Маппинг путей на типы данных
     * @return void
     */
    private function addBaseTypesForFieldsWithoutRules(array &$laravelRules, array $dataTypes): void
    {
        foreach ($dataTypes as $field => $dataType) {
            if (! isset($laravelRules[$field])) {
                $baseType = $this->dataTypeMapper->toLaravelRule($dataType);
                if ($baseType !== null) {
                    $laravelRules[$field] = [$baseType];
                }
            } elseif (str_ends_with($field, ValidationConstants::ARRAY_ELEMENT_WILDCARD) && $this->dataTypeMapper->isArrayType($dataType)) {
                // Для элементов массивов с data_type: 'json' или 'array' убеждаемся, что правило 'array' присутствует
                $this->ruleArrayManipulator->ensureArrayRule($laravelRules[$field]);
                $this->ensureArrayRulePosition($laravelRules[$field]);
            }
        }
    }

    /**
     * Проверить, есть ли правило JsonObject в массиве правил.
     *
     * @param array<int, string|object> $rules Массив правил
     * @return bool
     */
    private function hasJsonObjectRule(array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule instanceof JsonObject) {
                return true;
            }
        }
        return false;
    }

}

