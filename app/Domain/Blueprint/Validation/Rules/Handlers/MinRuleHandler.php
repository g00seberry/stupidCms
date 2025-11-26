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
    public function handle(Rule $rule, string $dataType): array
    {
        if (! $rule instanceof MinRule) {
            throw new \InvalidArgumentException('Expected MinRule instance');
        }

        $params = $rule->getParams();
        $value = $params['value'] ?? 0;
        $ruleDataType = $params['data_type'] ?? 'string';

        if (! is_numeric($value)) {
            return ['min:0'];
        }

        $minValue = $ruleDataType === 'float' ? (float) $value : (int) $value;

        return ["min:{$minValue}"];
    }
}

