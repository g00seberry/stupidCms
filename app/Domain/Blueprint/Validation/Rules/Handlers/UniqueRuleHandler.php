<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Domain\Blueprint\Validation\Rules\UniqueRule;

/**
 * Обработчик правила UniqueRule.
 *
 * Преобразует UniqueRule в строку Laravel правила валидации
 * (например, 'unique:table,column' или 'unique:table,column,except,id').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class UniqueRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'unique';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule, string $dataType): array
    {
        if (! $rule instanceof UniqueRule) {
            throw new \InvalidArgumentException('Expected UniqueRule instance');
        }

        $table = $rule->getTable();
        $column = $rule->getColumn();
        $exceptColumn = $rule->getExceptColumn();
        $exceptValue = $rule->getExceptValue();

        $ruleString = "unique:{$table},{$column}";

        // Добавляем исключение (для обновления)
        // Формат: unique:table,column,except,exceptColumn
        if ($exceptColumn !== null && $exceptValue !== null) {
            $ruleString .= ",{$exceptValue},{$exceptColumn}";
        }

        // Примечание: WHERE условия для unique лучше реализовать через Rule::unique()->where()
        // в будущих версиях, так как строковый формат не поддерживает сложные WHERE

        return [$ruleString];
    }
}

