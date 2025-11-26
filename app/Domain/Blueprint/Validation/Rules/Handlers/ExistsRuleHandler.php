<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\ExistsRule;
use App\Domain\Blueprint\Validation\Rules\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Обработчик правила ExistsRule.
 *
 * Преобразует ExistsRule в строку Laravel правила валидации
 * (например, 'exists:table,column').
 * Для WHERE условий использует Rule объекты Laravel.
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
        $whereColumn = $rule->getWhereColumn();
        $whereValue = $rule->getWhereValue();

        // Если есть WHERE условие, используем Rule объект Laravel
        if ($whereColumn !== null && $whereValue !== null) {
            $laravelRule = \Illuminate\Validation\Rule::exists($table, $column);
            
            // Добавляем WHERE условие
            $laravelRule->where($whereColumn, $whereValue);
            
            return [$laravelRule];
        }

        // Для простых случаев используем строковый формат
        $ruleString = "exists:{$table},{$column}";

        return [$ruleString];
    }
}

