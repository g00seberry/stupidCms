<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Adapters;

use App\Domain\Blueprint\Validation\Rules\Handlers\RuleHandlerRegistry;
use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Domain\Blueprint\Validation\Rules\RuleSet;

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
                $baseType = $this->getBaseTypeForDataType($dataTypes[$field]);
                if ($baseType !== null) {
                    // Вставляем базовый тип после required/nullable, но перед остальными правилами
                    $this->insertBaseType($fieldRules, $baseType);
                }
            }

            if (! empty($fieldRules)) {
                $laravelRules[$field] = $fieldRules;
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

}

