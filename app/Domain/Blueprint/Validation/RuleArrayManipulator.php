<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation;

/**
 * Манипулятор массивов правил валидации.
 *
 * Инкапсулирует логику вставки и манипуляции правилами в массивах.
 *
 * @package App\Domain\Blueprint\Validation
 */
final class RuleArrayManipulator
{
    /**
     * Вставить правило в массив после required/nullable правил.
     *
     * @param array<int, string|object> $rules Массив правил (изменяется по ссылке)
     * @param string|object $ruleToInsert Правило для вставки
     * @return void
     */
    public function insertAfterRequired(array &$rules, string|object $ruleToInsert): void
    {
        // Ищем позицию после required/nullable
        $insertPosition = 0;
        foreach ($rules as $index => $rule) {
            if ($this->isRequiredOrNullable($rule)) {
                $insertPosition = $index + 1;
            } else {
                break;
            }
        }

        // Вставляем правило
        array_splice($rules, $insertPosition, 0, [$ruleToInsert]);
    }

    /**
     * Убедиться, что правило 'array' присутствует в массиве правил.
     *
     * Вставляет правило 'array' после required/nullable, если его ещё нет.
     *
     * @param array<int, string|object> $rules Массив правил (изменяется по ссылке)
     * @return void
     */
    public function ensureArrayRule(array &$rules): void
    {
        // Проверяем, есть ли уже правило 'array'
        if ($this->hasArrayRule($rules)) {
            return;
        }

        // Вставляем 'array' после required/nullable
        $this->insertAfterRequired($rules, ValidationConstants::RULE_ARRAY);
    }

    /**
     * Объединить два массива правил, удаляя дубликаты и сохраняя порядок.
     *
     * @param array<int, string|object> $existing Существующие правила
     * @param array<int, string|object> $new Новые правила
     * @return array<int, string|object> Объединённый массив правил
     */
    public function mergeRules(array $existing, array $new): array
    {
        $merged = array_merge($existing, $new);
        // Удаляем дубликаты, сохраняя порядок
        return array_values(array_unique($merged, SORT_REGULAR));
    }

    /**
     * Проверить, является ли правило required или nullable.
     *
     * @param string|object $rule Правило для проверки
     * @return bool
     */
    private function isRequiredOrNullable(string|object $rule): bool
    {
        if (! is_string($rule)) {
            return false;
        }

        return in_array($rule, ValidationConstants::getRequiredNullableRules(), true);
    }

    /**
     * Проверить, есть ли правило 'array' в массиве правил.
     *
     * @param array<int, string|object> $rules Массив правил
     * @return bool
     */
    private function hasArrayRule(array $rules): bool
    {
        return in_array(ValidationConstants::RULE_ARRAY, $rules, true);
    }
}

