<?php

declare(strict_types=1);

namespace App\Domain\Search\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object для фильтра поиска по терму.
 *
 * Представляет фильтр по терму таксономии в формате "taxonomy_id:slug".
 *
 * @package App\Domain\Search\ValueObjects
 */
final class SearchTermFilter
{
    /**
     * @param int $taxonomyId ID таксономии
     * @param string $slug Slug терма
     */
    private function __construct(
        public readonly int $taxonomyId,
        public readonly string $slug
    ) {
    }

    /**
     * Создать фильтр из строки формата "taxonomy_id:slug".
     *
     * @param string $value Строка в формате "taxonomy_id:slug"
     * @return self Фильтр терма
     * @throws \InvalidArgumentException Если формат невалиден
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '' || ! str_contains($value, ':')) {
            throw new InvalidArgumentException('Term filter must be in format taxonomy_id:slug.');
        }

        [$taxonomyIdStr, $slug] = array_map('trim', explode(':', $value, 2));

        if ($taxonomyIdStr === '' || $slug === '') {
            throw new InvalidArgumentException('Both taxonomy_id and slug must be non-empty.');
        }

        $taxonomyId = filter_var($taxonomyIdStr, FILTER_VALIDATE_INT);
        if ($taxonomyId === false) {
            throw new InvalidArgumentException('Taxonomy ID must be a valid integer.');
        }

        return new self($taxonomyId, $slug);
    }
}


