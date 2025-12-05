<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\FieldComparisonRule;
use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Rules\FieldComparison;

/**
 * Обработчик правила FieldComparisonRule.
 *
 * Преобразует FieldComparisonRule в Laravel custom rule FieldComparison.
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class FieldComparisonRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'field_comparison';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof FieldComparisonRule) {
            throw new \InvalidArgumentException('Expected FieldComparisonRule instance');
        }

        // Возвращаем экземпляр Laravel custom rule
        return [new FieldComparison(
            $rule->getOperator(),
            $rule->getOtherField(),
            $rule->getConstantValue()
        )];
    }
}

