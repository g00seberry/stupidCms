<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Domain\Blueprint\Validation\Rules\UniqueRule;
use Illuminate\Validation\Rules\Unique;

/**
 * Обработчик правила UniqueRule.
 *
 * Преобразует UniqueRule в строку Laravel правила валидации
 * (например, 'unique:table,column' или 'unique:table,column,except,id').
 * Для WHERE условий использует Rule объекты Laravel.
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
        $whereColumn = $rule->getWhereColumn();
        $whereValue = $rule->getWhereValue();

        // Если есть WHERE условие, используем Rule объект Laravel
        if ($whereColumn !== null && $whereValue !== null) {
            $laravelRule = \Illuminate\Validation\Rule::unique($table, $column);
            
            // Добавляем WHERE условие
            $laravelRule->where($whereColumn, $whereValue);
            
            // Добавляем исключение (для обновления)
            if ($exceptColumn !== null && $exceptValue !== null) {
                $laravelRule->ignore($exceptValue, $exceptColumn);
            }
            
            return [$laravelRule];
        }

        // Для простых случаев используем строковый формат
        $ruleString = "unique:{$table},{$column}";

        // Добавляем исключение (для обновления)
        // Формат: unique:table,column,except,exceptColumn
        if ($exceptColumn !== null && $exceptValue !== null) {
            $ruleString .= ",{$exceptValue},{$exceptColumn}";
        }

        return [$ruleString];
    }
}

