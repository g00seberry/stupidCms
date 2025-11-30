<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\NullableRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Обработчик правила NullableRule.
 *
 * Преобразует NullableRule в строку Laravel правила валидации ('nullable').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class NullableRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'nullable';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof NullableRule) {
            throw new \InvalidArgumentException('Expected NullableRule instance');
        }

        return ['nullable'];
    }
}

