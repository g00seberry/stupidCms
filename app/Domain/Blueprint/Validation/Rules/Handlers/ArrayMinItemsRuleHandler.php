<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\ArrayMinItemsRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Обработчик правила ArrayMinItemsRule.
 *
 * Преобразует ArrayMinItemsRule в строку Laravel правила валидации (например, 'min:2').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class ArrayMinItemsRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'array_min_items';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof ArrayMinItemsRule) {
            throw new \InvalidArgumentException('Expected ArrayMinItemsRule instance');
        }

        $params = $rule->getParams();
        $value = $params['value'] ?? 0;

        return ['min:'.$value];
    }
}

