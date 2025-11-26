<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\ArrayUniqueRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Обработчик правила ArrayUniqueRule.
 *
 * Преобразует ArrayUniqueRule в строку Laravel правила валидации ('distinct').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class ArrayUniqueRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'array_unique';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule, string $dataType): array
    {
        if (! $rule instanceof ArrayUniqueRule) {
            throw new \InvalidArgumentException('Expected ArrayUniqueRule instance');
        }

        return ['distinct'];
    }
}

