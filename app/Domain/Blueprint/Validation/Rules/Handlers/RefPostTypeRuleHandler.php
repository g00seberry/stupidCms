<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\RefPostTypeRule;
use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Rules\RefPostType;

/**
 * Обработчик правила RefPostTypeRule.
 *
 * Преобразует RefPostTypeRule в Laravel custom rule RefPostType.
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class RefPostTypeRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'ref_post_type';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof RefPostTypeRule) {
            throw new \InvalidArgumentException('Expected RefPostTypeRule instance');
        }

        // Возвращаем экземпляр Laravel custom rule
        return [new RefPostType(
            $rule->getAllowedPostTypeIds(),
            $rule->getPathFullPath()
        )];
    }
}

