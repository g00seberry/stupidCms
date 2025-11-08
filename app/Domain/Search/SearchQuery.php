<?php

declare(strict_types=1);

namespace App\Domain\Search;

use App\Domain\Search\ValueObjects\SearchTermFilter;
use Carbon\CarbonImmutable;

final class SearchQuery
{
    /**
     * @param list<SearchTermFilter> $terms
     */
    public function __construct(
        private readonly ?string $query,
        private readonly array $postTypes,
        private readonly array $terms,
        private readonly ?CarbonImmutable $from,
        private readonly ?CarbonImmutable $to,
        private readonly int $page,
        private readonly int $perPage
    ) {
    }

    public function query(): ?string
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function postTypes(): array
    {
        return $this->postTypes;
    }

    /**
     * @return list<SearchTermFilter>
     */
    public function terms(): array
    {
        return $this->terms;
    }

    public function from(): ?CarbonImmutable
    {
        return $this->from;
    }

    public function to(): ?CarbonImmutable
    {
        return $this->to;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function isBlank(): bool
    {
        return $this->query === null || trim($this->query) === '';
    }
}


