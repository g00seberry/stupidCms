<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Adapters;

use App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerRegistry;
use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Domain\Blueprint\Validation\Rules\RuleSet;
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
     */
    public function __construct(
        private readonly RuleHandlerRegistry $registry
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
        $arrayElementFields = [];
        foreach ($dataTypes as $field => $dataType) {
            if (str_ends_with($field, '.*') && ($dataType === 'json' || $dataType === 'array')) {
                $arrayElementFields[] = $field;
            }
        }

        // Добавляем правило 'array' для элементов массивов с data_type: 'json' или 'array'
        foreach ($arrayElementFields as $field) {
            if (!isset($laravelRules[$field])) {
                $laravelRules[$field] = ['array'];
            } elseif (!in_array('array', $laravelRules[$field], true)) {
                // Вставляем 'array' в начало (после required/nullable, если есть)
                $insertPosition = 0;
                foreach ($laravelRules[$field] as $index => $rule) {
                    if (in_array($rule, ['required', 'nullable'], true)) {
                        $insertPosition = $index + 1;
                    } else {
                        break;
                    }
                }
                array_splice($laravelRules[$field], $insertPosition, 0, ['array']);
            }
        }

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
                // Специальная обработка для типа 'array' (для массивов внутри массивов объектов)
                if ($dataTypes[$field] === 'array') {
                    if (!in_array('array', $fieldRules, true)) {
                        $this->insertBaseType($fieldRules, 'array');
                    }
                } else {
                    $baseType = $this->getBaseTypeForDataType($dataTypes[$field]);
                    if ($baseType !== null) {
                    // Для элементов массивов (заканчивающихся на .*) с data_type: 'json' или 'array'
                    // правило 'array' уже добавлено в начале метода
                    if (str_ends_with($field, '.*') && ($dataTypes[$field] === 'json' || $dataTypes[$field] === 'array')) {
                        // Убеждаемся, что правило 'array' присутствует (должно быть уже добавлено)
                        if (!in_array('array', $fieldRules, true)) {
                            $this->insertBaseType($fieldRules, 'array');
                        }
                    } elseif ($dataTypes[$field] === 'json' && !str_ends_with($field, '.*')) {
                            // Для json типа с cardinality: 'one' добавляем правило 'array' и проверку на объект
                            if (!in_array('array', $fieldRules, true)) {
                                $this->insertBaseType($fieldRules, 'array');
                            }
                            // Добавляем проверку, что это объект, а не массив
                            // Используем кастомное правило JsonObject
                            if (!in_array(JsonObject::class, $fieldRules, true) && !$this->hasJsonObjectRule($fieldRules)) {
                                $fieldRules[] = new JsonObject();
                            }
                        } else {
                            // Вставляем базовый тип после required/nullable, но перед остальными правилами
                            $this->insertBaseType($fieldRules, $baseType);
                        }
                    }
                }
            }

            if (! empty($fieldRules)) {
                // Если правило 'array' уже было добавлено для этого поля (например, для элементов массивов),
                // убеждаемся, что оно не потеряется при объединении
                if (isset($laravelRules[$field]) && in_array('array', $laravelRules[$field], true)) {
                    // Объединяем правила, сохраняя 'array' в правильной позиции
                    $existingRules = $laravelRules[$field];
                    $mergedRules = array_merge($existingRules, $fieldRules);
                    // Удаляем дубликаты, сохраняя порядок
                    $laravelRules[$field] = array_values(array_unique($mergedRules, SORT_REGULAR));
                    // Убеждаемся, что 'array' в правильной позиции (после required/nullable)
                    $arrayIndex = array_search('array', $laravelRules[$field], true);
                    if ($arrayIndex !== false && $arrayIndex > 0) {
                        $hasNonRequiredBefore = false;
                        for ($i = 0; $i < $arrayIndex; $i++) {
                            if (!in_array($laravelRules[$field][$i], ['required', 'nullable'], true)) {
                                $hasNonRequiredBefore = true;
                                break;
                            }
                        }
                        if ($hasNonRequiredBefore) {
                            unset($laravelRules[$field][$arrayIndex]);
                            $laravelRules[$field] = array_values($laravelRules[$field]);
                            $insertPosition = 0;
                            foreach ($laravelRules[$field] as $index => $rule) {
                                if (in_array($rule, ['required', 'nullable'], true)) {
                                    $insertPosition = $index + 1;
                                } else {
                                    break;
                                }
                            }
                            array_splice($laravelRules[$field], $insertPosition, 0, ['array']);
                        }
                    }
                } else {
                    $laravelRules[$field] = $fieldRules;
                }
            }
        }

        // Добавляем базовый тип для полей из dataTypes, которые не имеют правил в ruleSet
        // Это нужно для элементов массивов с data_type: 'json', которые должны быть объектами
        foreach ($dataTypes as $field => $dataType) {
            if (! isset($laravelRules[$field])) {
                $baseType = $this->getBaseTypeForDataType($dataType);
                if ($baseType !== null) {
                    $laravelRules[$field] = [$baseType];
                }
            } elseif (str_ends_with($field, '.*') && ($dataType === 'json' || $dataType === 'array')) {
                // Для элементов массивов с data_type: 'json' или 'array' убеждаемся, что правило 'array' присутствует
                // даже если есть другие правила (например, для вложенных полей)
                // Правило 'array' должно быть ПЕРВЫМ, чтобы Laravel правильно валидировал вложенные поля
                $fieldRules = $laravelRules[$field];
                if (!in_array('array', $fieldRules, true)) {
                    // Вставляем 'array' в начало массива правил (после required/nullable, если есть)
                    $insertPosition = 0;
                    foreach ($fieldRules as $index => $rule) {
                        if (in_array($rule, ['required', 'nullable'], true)) {
                            $insertPosition = $index + 1;
                        } else {
                            break;
                        }
                    }
                    array_splice($fieldRules, $insertPosition, 0, ['array']);
                    $laravelRules[$field] = $fieldRules;
                } else {
                    // Если правило 'array' уже есть, убеждаемся, что оно в правильной позиции
                    // (после required/nullable, но перед остальными правилами)
                    $arrayIndex = array_search('array', $fieldRules, true);
                    if ($arrayIndex !== false && $arrayIndex > 0) {
                        // Проверяем, что перед 'array' только required/nullable
                        $hasNonRequiredBefore = false;
                        for ($i = 0; $i < $arrayIndex; $i++) {
                            if (!in_array($fieldRules[$i], ['required', 'nullable'], true)) {
                                $hasNonRequiredBefore = true;
                                break;
                            }
                        }
                        if ($hasNonRequiredBefore) {
                            // Перемещаем 'array' в правильную позицию
                            unset($fieldRules[$arrayIndex]);
                            $fieldRules = array_values($fieldRules);
                            $insertPosition = 0;
                            foreach ($fieldRules as $index => $rule) {
                                if (in_array($rule, ['required', 'nullable'], true)) {
                                    $insertPosition = $index + 1;
                                } else {
                                    break;
                                }
                            }
                            array_splice($fieldRules, $insertPosition, 0, ['array']);
                            $laravelRules[$field] = $fieldRules;
                        }
                    }
                }
            }
        }

        return $laravelRules;
    }


    /**
     * Получить базовый тип валидации Laravel по data_type Path.
     *
     * @param string $dataType Тип данных Path
     * @return string|null Правило валидации Laravel или null
     */
    private function getBaseTypeForDataType(string $dataType): ?string
    {
        return match ($dataType) {
            'string', 'text' => 'string',
            'int' => 'integer',
            'float' => 'numeric',
            'bool' => 'boolean',
            'date' => 'date',
            'datetime' => 'date',
            'json' => 'array',
            'ref' => 'integer', // ref хранится как ID (integer)
            default => null,
        };
    }

    /**
     * Вставить базовый тип в массив правил после required/nullable.
     *
     * @param array<int, string> $rules Массив правил (изменяется по ссылке)
     * @param string $baseType Базовый тип (string, integer, numeric и т.д.)
     * @return void
     */
    private function insertBaseType(array &$rules, string $baseType): void
    {
        // Ищем позицию после required/nullable
        $insertPosition = 0;
        foreach ($rules as $index => $rule) {
            if (in_array($rule, ['required', 'nullable'], true)) {
                $insertPosition = $index + 1;
            } else {
                break;
            }
        }

        // Вставляем базовый тип
        array_splice($rules, $insertPosition, 0, [$baseType]);
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

