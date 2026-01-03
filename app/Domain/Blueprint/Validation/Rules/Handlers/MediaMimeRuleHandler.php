<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules\Handlers;

use App\Domain\Blueprint\Validation\Rules\MediaMimeRule;
use App\Domain\Blueprint\Validation\Rules\Rule;
use App\Rules\MediaMime;

/**
 * Обработчик правила MediaMimeRule.
 *
 * Преобразует MediaMimeRule в Laravel custom rule MediaMime.
 *
 * @package App\Domain\Blueprint\Validation\Rules\Handlers
 */
final class MediaMimeRuleHandler implements RuleHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function supports(string $ruleType): bool
    {
        return $ruleType === 'media_mime';
    }

    /**
     * @inheritDoc
     */
    public function handle(Rule $rule): array
    {
        if (! $rule instanceof MediaMimeRule) {
            throw new \InvalidArgumentException('Expected MediaMimeRule instance');
        }

        // Возвращаем экземпляр Laravel custom rule
        return [new MediaMime(
            $rule->getAllowedMimeTypes(),
            $rule->getPathFullPath()
        )];
    }
}

