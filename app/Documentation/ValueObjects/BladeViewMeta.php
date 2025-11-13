<?php

declare(strict_types=1);

namespace App\Documentation\ValueObjects;

final readonly class BladeViewMeta
{
    /**
     * @param array<string> $variables
     * @param array<string> $includes
     */
    public function __construct(
        public string $role,
        public array $variables = [],
        public ?string $extends = null,
        public array $includes = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'variables' => $this->variables,
            'extends' => $this->extends,
            'includes' => $this->includes,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            role: $data['role'],
            variables: $data['variables'] ?? [],
            extends: $data['extends'] ?? null,
            includes: $data['includes'] ?? [],
        );
    }
}

