<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\PatternRule;
use App\Domain\Blueprint\Validation\Rules\Rule;

/**
 * Обработчик правила PatternRule.
 *
 * Преобразует PatternRule в строку Laravel правила валидации (например, 'regex:/pattern/').
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class PatternRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'pattern';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof PatternRule) {
            throw new \InvalidArgumentException('Expected PatternRule instance');
        }

        $params = $rule->getParams();
        $pattern = $params['pattern'] ?? '.*';

        if ($pattern === '') {
            return ['regex:/.*/'];
        }

        // Проверяем, является ли паттерн уже валидным regex (начинается и заканчивается на /)
        if (preg_match('/^\/.+\/[gimsxADSUXJu]*$/', $pattern)) {
            // Паттерн уже в формате /pattern/flags - используем как есть
            return ["regex:{$pattern}"];
        }

        // Экранируем слэши внутри паттерна
        $escapedPattern = str_replace('/', '\/', $pattern);

        return ["regex:/{$escapedPattern}/"];
    }
}

