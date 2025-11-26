<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\ExistsRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Обработчик правила ExistsRule.
 *
 * Преобразует ExistsRule в строку Laravel правила валидации
 * (например, 'exists:table,column').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class ExistsRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'exists';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule, string $dataType): array
    {
        if (! $rule instanceof ExistsRule) {
            throw new \InvalidArgumentException('Expected ExistsRule instance');
        }

        $table = $rule->getTable();
        $column = $rule->getColumn();

        $ruleString = "exists:{$table},{$column}";

        // Примечание: WHERE условия для exists лучше реализовать через Rule::exists()->where()
        // в будущих версиях, так как строковый формат не поддерживает сложные WHERE

        return [$ruleString];
    }
}

