<?php

declare(strict_types=1);

namespace App\Domain\Search\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object для фильтра поиска по терму.
 *
 * Представляет фильтр по терму таксономии в формате "taxonomy_id:term_id".
 *
 * @package App\Domain\Search\ValueObjects
 */
final class SearchTermFilter
{
    /**
     * @param int $taxonomyId ID таксономии
     * @param int $termId ID терма
     */
    private function __construct(
        public readonly int $taxonomyId,
        public readonly int $termId
    ) {
    }

    /**
     * Создать фильтр из строки формата "taxonomy_id:term_id".
     *
     * @param string $value Строка в формате "taxonomy_id:term_id"
     * @return self Фильтр терма
     * @throws \InvalidArgumentException Если формат невалиден
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '' || ! str_contains($value, ':')) {
            throw new InvalidArgumentException('Term filter must be in format taxonomy_id:term_id.');
        }

        [$taxonomyIdStr, $termIdStr] = array_map('trim', explode(':', $value, 2));

        if ($taxonomyIdStr === '' || $termIdStr === '') {
            throw new InvalidArgumentException('Both taxonomy_id and term_id must be non-empty.');
        }

        $taxonomyId = filter_var($taxonomyIdStr, FILTER_VALIDATE_INT);
        if ($taxonomyId === false) {
            throw new InvalidArgumentException('Taxonomy ID must be a valid integer.');
        }

        $termId = filter_var($termIdStr, FILTER_VALIDATE_INT);
        if ($termId === false) {
            throw new InvalidArgumentException('Term ID must be a valid integer.');
        }

        return new self($taxonomyId, $termId);
    }
}


