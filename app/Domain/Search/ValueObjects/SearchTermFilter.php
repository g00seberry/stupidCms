<?php

declare(strict_types=1);

namespace App\Domain\Search\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object для фильтра поиска по терму.
 *
 * Представляет фильтр по терму таксономии в формате "taxonomy:slug".
 *
 * @package App\Domain\Search\ValueObjects
 */
final class SearchTermFilter
{
    /**
     * @param string $taxonomy Slug таксономии
     * @param string $slug Slug терма
     */
    private function __construct(
        public readonly string $taxonomy,
        public readonly string $slug
    ) {
    }

    /**
     * Создать фильтр из строки формата "taxonomy:slug".
     *
     * @param string $value Строка в формате "taxonomy:slug"
     * @return self Фильтр терма
     * @throws \InvalidArgumentException Если формат невалиден
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '' || ! str_contains($value, ':')) {
            throw new InvalidArgumentException('Term filter must be in format taxonomy:slug.');
        }

        [$taxonomy, $slug] = array_map('trim', explode(':', $value, 2));

        if ($taxonomy === '' || $slug === '') {
            throw new InvalidArgumentException('Both taxonomy and slug must be non-empty.');
        }

        return new self($taxonomy, $slug);
    }
}


