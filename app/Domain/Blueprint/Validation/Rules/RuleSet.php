<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Набор правил валидации для полей.
 *
 * Хранит правила валидации, сгруппированные по путям полей.
 * Используется для построения полного набора правил валидации для Blueprint.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class RuleSet
{
    /**
     * Правила валидации, сгруппированные по путям полей.
     *
     * @var array<string, list<Rule>>
     */
    private array $rules = [];

    /**
     * Добавить правило для поля.
     *
     * @param string $fieldPath Путь поля в точечной нотации (например, 'data_json.title')
     * @param \App\Domain\Blueprint\Validation\Rules\Rule $rule Правило валидации
     * @return void
     */
    public function addRule(string $fieldPath, Rule $rule): void
    {
        if (! isset($this->rules[$fieldPath])) {
            $this->rules[$fieldPath] = [];
        }

        $this->rules[$fieldPath][] = $rule;
    }

    /**
     * Получить правила для конкретного поля.
     *
     * @param string $fieldPath Путь поля
     * @return list<\App\Domain\Blueprint\Validation\Rules\Rule> Массив правил или пустой массив
     */
    public function getRulesForField(string $fieldPath): array
    {
        return $this->rules[$fieldPath] ?? [];
    }

    /**
     * Получить все правила для всех полей.
     *
     * @return array<string, list<\App\Domain\Blueprint\Validation\Rules\Rule>>
     *         Массив, где ключи - пути полей, значения - массивы правил
     */
    public function getAllRules(): array
    {
        return $this->rules;
    }

    /**
     * Проверить, есть ли правила для поля.
     *
     * @param string $fieldPath Путь поля
     * @return bool
     */
    public function hasRulesForField(string $fieldPath): bool
    {
        return isset($this->rules[$fieldPath]) && ! empty($this->rules[$fieldPath]);
    }

    /**
     * Получить список всех путей полей, для которых есть правила.
     *
     * @return list<string>
     */
    public function getFieldPaths(): array
    {
        return array_keys($this->rules);
    }

    /**
     * Проверить, есть ли какие-либо правила.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->rules);
    }
}

