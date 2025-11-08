<?php

declare(strict_types=1);

namespace App\Domain\Search\ValueObjects;

use InvalidArgumentException;

final class SearchTermFilter
{
    private function __construct(
        public readonly string $taxonomy,
        public readonly string $slug
    ) {
    }

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


