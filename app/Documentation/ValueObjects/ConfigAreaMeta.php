<?php

declare(strict_types=1);

namespace App\Documentation\ValueObjects;

final readonly class ConfigAreaMeta
{
    /**
     * @param array<string> $keys
     * @param array<string> $sections
     */
    public function __construct(
        public array $keys = [],
        public array $sections = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'keys' => $this->keys,
            'sections' => $this->sections,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            keys: $data['keys'] ?? [],
            sections: $data['sections'] ?? [],
        );
    }
}

