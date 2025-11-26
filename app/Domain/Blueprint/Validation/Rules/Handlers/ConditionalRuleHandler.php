<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\ConditionalRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Обработчик правила ConditionalRule.
 *
 * Преобразует ConditionalRule в строку Laravel правила валидации
 * (например, 'required_if:is_published,true').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class ConditionalRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return in_array($ruleType, ['required_if', 'prohibited_unless', 'required_unless', 'prohibited_if'], true);
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule, string $dataType): array
    {
        if (! $rule instanceof ConditionalRule) {
            throw new \InvalidArgumentException('Expected ConditionalRule instance');
        }

        $type = $rule->getType();
        $field = $rule->getField();
        $value = $rule->getValue();
        $operator = $rule->getOperator();

        // Преобразуем значение в строку для Laravel
        $valueString = $this->formatConditionValue($value);

        // Для простых случаев (operator == '==') используем стандартный формат Laravel
        if ($operator === '==' || $operator === null) {
            return ["{$type}:{$field},{$valueString}"];
        }

        // Для других операторов используем расширенный формат
        // В Laravel это можно реализовать через custom rule или sometimes
        // Пока используем простой формат, расширение будет в следующих версиях
        return ["{$type}:{$field},{$valueString}"];
    }

    /**
     * Форматировать значение условия для Laravel правила.
     *
     * @param mixed $value Значение условия
     * @return string Отформатированное значение
     */
    private function formatConditionValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_array($value)) {
            return implode(',', array_map(fn ($v) => $this->formatConditionValue($v), $value));
        }

        return (string) $value;
    }
}

