<?php

namespace App\Support\Slug;

final class SlugOptions
{
    /**
     * @param callable(string, SlugOptions): string|null $postProcess
     */
    public function __construct(
        public string $delimiter = '-',
        public bool $toLower = true,
        public bool $asciiOnly = true,
        public int $maxLength = 120,
        public string $scheme = 'ru_basic',
        public array $customMap = [],
        public array $stopWords = [],
        public array $reserved = [],
        public mixed $postProcess = null,
    ) {}
}

