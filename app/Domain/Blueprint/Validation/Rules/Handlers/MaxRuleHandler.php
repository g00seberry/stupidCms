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
    public function handle(Rule $rule, string $dataType): array
    {
        if (! $rule instanceof MaxRule) {
            throw new \InvalidArgumentException('Expected MaxRule instance');
        }

        $params = $rule->getParams();
        $value = $params['value'] ?? PHP_INT_MAX;
        $ruleDataType = $params['data_type'] ?? 'string';

        if (! is_numeric($value)) {
            return ['max:'.PHP_INT_MAX];
        }

        $maxValue = $ruleDataType === 'float' ? (float) $value : (int) $value;

        return ["max:{$maxValue}"];
    }
}

