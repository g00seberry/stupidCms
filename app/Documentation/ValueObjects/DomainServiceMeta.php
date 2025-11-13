<?php

declare(strict_types=1);

namespace App\Documentation\ValueObjects;

final readonly class DomainServiceMeta
{
    /**
     * @param array<string> $methods
     * @param array<string> $dependencies
     */
    public function __construct(
        public array $methods = [],
        public array $dependencies = [],
        public ?string $interface = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'methods' => $this->methods,
            'dependencies' => $this->dependencies,
            'interface' => $this->interface,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            methods: $data['methods'] ?? [],
            dependencies: $data['dependencies'] ?? [],
            interface: $data['interface'] ?? null,
        );
    }
}

