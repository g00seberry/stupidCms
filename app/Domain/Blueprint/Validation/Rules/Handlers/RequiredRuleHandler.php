<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\RequiredRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Обработчик правила RequiredRule.
 *
 * Преобразует RequiredRule в строку Laravel правила валидации ('required').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class RequiredRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'required';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof RequiredRule) {
            throw new \InvalidArgumentException('Expected RequiredRule instance');
        }

        return ['required'];
    }
}

