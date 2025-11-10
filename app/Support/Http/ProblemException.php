<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use RuntimeException;

abstract class ProblemException extends RuntimeException
{
    private readonly ?string $problemCode;

    /**
     * @param array<string, mixed> $extensions
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly ProblemType $type,
        private readonly ?string $detail = null,
        private readonly array $extensions = [],
        private readonly array $headers = [],
        private readonly ?string $title = null,
        private readonly ?int $status = null,
        ?string $code = null,
    ) {
        $this->problemCode = $code;
        parent::__construct($detail ?? $type->defaultDetail());
    }

    public function type(): ProblemType
    {
        return $this->type;
    }

    public function detail(): ?string
    {
        return $this->detail;
    }

    public function userFriendlyDetail(): string
    {
        return $this->detail ?? $this->type->defaultDetail();
    }

    /**
     * @return array<string, mixed>
     */
    public function extensions(): array
    {
        return $this->extensions;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function status(): ?int
    {
        return $this->status;
    }

    public function code(): ?string
    {
        return $this->problemCode;
    }

    public function apply(JsonResponse $response): JsonResponse
    {
        return $this->configureResponse($response);
    }

    protected function configureResponse(JsonResponse $response): JsonResponse
    {
        return $response;
    }
}
