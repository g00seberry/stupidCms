<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\ArrayMaxItemsRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Обработчик правила ArrayMaxItemsRule.
 *
 * Преобразует ArrayMaxItemsRule в строку Laravel правила валидации (например, 'max:10').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class ArrayMaxItemsRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'array_max_items';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule, string $dataType): array
    {
        if (! $rule instanceof ArrayMaxItemsRule) {
            throw new \InvalidArgumentException('Expected ArrayMaxItemsRule instance');
        }

        $params = $rule->getParams();
        $value = $params['value'] ?? PHP_INT_MAX;

        return ['max:'.$value];
    }
}

