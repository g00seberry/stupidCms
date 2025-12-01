<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\DistinctRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Обработчик правила DistinctRule.
 *
 * Преобразует DistinctRule в строку Laravel правила валидации ('distinct').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class DistinctRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'distinct';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof DistinctRule) {
            throw new \InvalidArgumentException('Expected DistinctRule instance');
        }

        return ['distinct'];
    }
}
