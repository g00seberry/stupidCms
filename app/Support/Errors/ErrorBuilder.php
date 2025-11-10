<?php

declare(strict_types=1);

namespace App\Support\Errors;

use InvalidArgumentException;

final class ErrorBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $meta = [];

    private string $detail;
    private string $uri;
    private string $title;
    private int $status;
    private ?string $traceId = null;

    public function __construct(private readonly ErrorType $type)
    {
        $this->uri = $type->uri;
        $this->title = $type->title;
        $this->status = $type->status;
        $this->detail = $type->defaultDetail;
    }

    public function detail(string $detail): self
    {
        $clone = clone $this;
        $clone->detail = $detail;

        return $clone;
    }

    public function title(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    public function status(int $status): self
    {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException(sprintf(
                'Status code must be a valid HTTP status (100-599). %d given.',
                $status,
            ));
        }

        $clone = clone $this;
        $clone->status = $status;

        return $clone;
    }

    public function type(string $uri): self
    {
        if (trim($uri) === '') {
            throw new InvalidArgumentException('Error type URI cannot be empty.');
        }

        $clone = clone $this;
        $clone->uri = $uri;

        return $clone;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function meta(array $meta): self
    {
        $this->assertMeta($meta);

        $clone = clone $this;
        $clone->meta = $meta;

        return $clone;
    }

    public function addMeta(string $key, mixed $value): self
    {
        if ($key === '' || trim($key) === '') {
            throw new InvalidArgumentException('Meta key cannot be empty.');
        }

        $clone = clone $this;
        $clone->meta[$key] = $value;

        return $clone;
    }

    public function traceId(?string $traceId): self
    {
        if ($traceId !== null && trim($traceId) === '') {
            $traceId = null;
        }

        $clone = clone $this;
        $clone->traceId = $traceId;

        return $clone;
    }

    public function build(): ErrorPayload
    {
        return ErrorPayload::create(
            type: $this->uri,
            title: $this->title,
            status: $this->status,
            code: $this->type->code,
            detail: $this->detail,
            meta: $this->meta,
            traceId: $this->traceId,
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function assertMeta(array $meta): void
    {
        foreach ($meta as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                throw new InvalidArgumentException('Meta keys must be non-empty strings.');
            }

            if (is_resource($value)) {
                throw new InvalidArgumentException(sprintf('Meta value for key "%s" cannot be a resource.', $key));
            }
        }
    }
}

