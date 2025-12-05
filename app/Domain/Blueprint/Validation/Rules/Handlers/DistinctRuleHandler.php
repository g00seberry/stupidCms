<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\DistinctRule;
use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Rules\DistinctObjects;

/**
 * Обработчик правила DistinctRule.
 *
 * Преобразует DistinctRule в кастомное правило DistinctObjects,
 * которое сравнивает элементы массива по их JSON-сериализации.
 * Это обеспечивает корректную работу с массивами объектов.
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class DistinctRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'distinct';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof DistinctRule) {
            throw new \InvalidArgumentException('Expected DistinctRule instance');
        }

        // Используем кастомное правило для сравнения объектов по JSON-сериализации
        return [new DistinctObjects()];
    }
}
