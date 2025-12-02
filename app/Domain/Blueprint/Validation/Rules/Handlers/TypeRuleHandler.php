<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Domain\Blueprint\Validation\Rules\TypeRule;

/**
 * Обработчик правила TypeRule.
 *
 * Преобразует TypeRule в строку Laravel правила валидации
 * (например, 'string', 'integer', 'numeric', 'boolean', 'date', 'array').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class TypeRuleHandler implements RuleHandlerInterface
{
    /**
     * Маппинг типов данных на Laravel правила.
     *
     * @var array<string, string>
     */
    private const TYPE_MAPPING = [
        'string' => 'string',
        'integer' => 'integer',
        'numeric' => 'numeric',
        'boolean' => 'boolean',
        'date' => 'date',
        'array' => 'array',
    ];

    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'type';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof TypeRule) {
            throw new \InvalidArgumentException('Expected TypeRule instance');
        }

        $type = $rule->getDataType();

        if (! isset(self::TYPE_MAPPING[$type])) {
            // Если тип не найден в маппинге, возвращаем пустой массив
            // (не должно происходить при корректной работе)
            return [];
        }

        return [self::TYPE_MAPPING[$type]];
    }
}

