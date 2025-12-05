<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\MaxRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Обработчик правила MaxRule.
 *
 * Преобразует MaxRule в строку Laravel правила валидации (например, 'max:500').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class MaxRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'max';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof MaxRule) {
            throw new \InvalidArgumentException('Expected MaxRule instance');
        }

        $value = $rule->getValue();

        if (! is_numeric($value)) {
            return ['max:'.PHP_INT_MAX];
        }

        $maxValue = is_float($value) ? (float) $value : (int) $value;

        return ["max:{$maxValue}"];
    }
}

