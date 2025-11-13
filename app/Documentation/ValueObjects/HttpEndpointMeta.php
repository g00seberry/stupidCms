<?php

declare(strict_types=1);

namespace App\Documentation\ValueObjects;

final readonly class HttpEndpointMeta
{
    /**
     * @param array<string, mixed> $parameters
     * @param array<int, array<string, mixed>> $responses
     */
    public function __construct(
        public string $method,
        public string $uri,
        public ?string $group = null,
        public ?string $auth = null,
        public array $parameters = [],
        public array $responses = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'uri' => $this->uri,
            'group' => $this->group,
            'auth' => $this->auth,
            'parameters' => $this->parameters,
            'responses' => $this->responses,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: $data['method'],
            uri: $data['uri'],
            group: $data['group'] ?? null,
            auth: $data['auth'] ?? null,
            parameters: $data['parameters'] ?? [],
            responses: $data['responses'] ?? [],
        );
    }
}

