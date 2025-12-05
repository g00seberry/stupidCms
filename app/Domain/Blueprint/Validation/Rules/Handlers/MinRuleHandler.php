<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\MinRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Обработчик правила MinRule.
 *
 * Преобразует MinRule в строку Laravel правила валидации (например, 'min:1').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class MinRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'min';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof MinRule) {
            throw new \InvalidArgumentException('Expected MinRule instance');
        }

        $value = $rule->getValue();

        if (! is_numeric($value)) {
            return ['min:0'];
        }

        $minValue = is_float($value) ? (float) $value : (int) $value;

        return ["min:{$minValue}"];
    }
}

