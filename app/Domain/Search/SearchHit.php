<?php

declare(strict_types=1);

namespace App\Domain\Search;

/**
 * @psalm-type HighlightMap = array<string, list<string>>
 */
final class SearchHit
{
    /**
     * @param HighlightMap $highlight
     */
    public function __construct(
        public readonly string $id,
        public readonly string $postType,
        public readonly string $slug,
        public readonly string $title,
        public readonly ?string $excerpt,
        public readonly ?float $score,
        public readonly array $highlight
    ) {
    }
}


