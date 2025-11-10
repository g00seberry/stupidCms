<?php

declare(strict_types=1);

namespace App\Support\Errors;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Canonical representation of an API error according to RFC 7807 + stupidCms extensions.
 *
 * All fields are required at transport level. Optional values are represented as null,
 * but the keys must always be present in serialized output.
 *
 * @psalm-type ErrorPayloadArray = array{
 *     type: string,
 *     title: string,
 *     status: int,
 *     code: string,
 *     detail: string,
 *     meta: array<string, mixed>,
 *     trace_id: string|null
 * }
 */
final class ErrorPayload implements JsonSerializable
{
    /**
     * @param array<string, mixed> $meta
     */
    private function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly int $status,
        public readonly ErrorCode $code,
        public readonly string $detail,
        public readonly array $meta = [],
        public readonly ?string $traceId = null,
    ) {
        $this->assertStatus($status);
        $this->assertNotEmpty($type, 'type');
        $this->assertNotEmpty($title, 'title');
        $this->assertMeta($meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function create(
        string $type,
        string $title,
        int $status,
        ErrorCode $code,
        string $detail,
        array $meta = [],
        ?string $traceId = null,
    ): self {
        return new self(
            type: $type,
            title: $title,
            status: $status,
            code: $code,
            detail: $detail,
            meta: $meta,
            traceId: $traceId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * @return self
     */
    public function withTraceId(?string $traceId): self
    {
        if ($traceId !== null && trim($traceId) === '') {
            $traceId = null;
        }

        return new self(
            type: $this->type,
            title: $this->title,
            status: $this->status,
            code: $this->code,
            detail: $this->detail,
            meta: $this->meta,
            traceId: $traceId,
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function withMeta(array $meta): self
    {
        $this->assertMeta($meta);

        return new self(
            type: $this->type,
            title: $this->title,
            status: $this->status,
            code: $this->code,
            detail: $this->detail,
            meta: $meta,
            traceId: $this->traceId,
        );
    }

    public function withAddedMeta(string $key, mixed $value): self
    {
        if ($key === '') {
            throw new InvalidArgumentException('Meta key cannot be empty.');
        }

        $meta = $this->meta;
        $meta[$key] = $value;

        return $this->withMeta($meta);
    }

    /**
     * @return ErrorPayloadArray
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'code' => $this->code->value,
            'detail' => $this->detail,
            'meta' => $this->meta,
            'trace_id' => $this->traceId,
        ];
    }

    /**
     * @return ErrorPayloadArray
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function assertStatus(int $status): void
    {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException(sprintf(
                'Status code must be a valid HTTP status (100-599). %d given.',
                $status,
            ));
        }
    }

    private function assertNotEmpty(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(sprintf('Field "%s" cannot be empty.', $field));
        }
    }

    /**
     * @param array<mixed> $meta
     */
    private function assertMeta(array $meta): void
    {
        foreach ($meta as $key => $value) {
            if (! is_string($key) || $key === '') {
                throw new InvalidArgumentException('Meta keys must be non-empty strings.');
            }

            if (is_resource($value)) {
                throw new InvalidArgumentException(sprintf('Meta value for key "%s" cannot be a resource.', $key));
            }
        }
    }
}

