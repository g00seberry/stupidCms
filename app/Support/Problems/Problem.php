<?php

declare(strict_types=1);

namespace App\Support\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;

final class Problem
{
    /**
     * @param array<string, mixed> $extensions
     * @param array<string, string> $headers
     */
    private function __construct(
        private ProblemType $type,
        private ?string $detail = null,
        private array $extensions = [],
        private array $headers = [],
        private ?string $title = null,
        private ?int $status = null,
        private ?string $code = null,
    ) {
    }

    public static function of(ProblemType $type): self
    {
        return new self($type);
    }

    public function type(): ProblemType
    {
        return $this->type;
    }

    public function detail(?string $detail): self
    {
        $clone = clone $this;
        $clone->detail = $detail;

        return $clone;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    /**
     * @param array<string, mixed> $extensions
     */
    public function extensions(array $extensions): self
    {
        $clone = clone $this;
        $clone->extensions = $extensions;

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * @param array<string, string> $headers
     */
    public function headers(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = $headers;

        return $clone;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function title(?string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function status(?int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;

        return $clone;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function code(?string $code): self
    {
        $clone = clone $this;
        $clone->code = $code;

        return $clone;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function userFriendlyDetail(): string
    {
        return $this->detail ?? $this->type->defaultDetail();
    }

    public function throw(): never
    {
        throw new HttpProblemException($this);
    }
}
