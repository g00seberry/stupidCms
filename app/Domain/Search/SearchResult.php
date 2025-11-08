<?php

declare(strict_types=1);

namespace App\Domain\Search;

/**
 * @psalm-type HitList = list<SearchHit>
 */
final class SearchResult
{
    /**
     * @param HitList $hits
     */
    public function __construct(
        private readonly array $hits,
        private readonly int $total,
        private readonly int $page,
        private readonly int $perPage,
        private readonly int $tookMs
    ) {
    }

    /**
     * @return HitList
     */
    public function hits(): array
    {
        return $this->hits;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function tookMs(): int
    {
        return $this->tookMs;
    }

    public static function empty(int $page, int $perPage): self
    {
        return new self([], 0, $page, $perPage, 0);
    }
}


