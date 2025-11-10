<?php

declare(strict_types=1);

namespace App\Http\Controllers\Traits;

use App\Support\Http\ProblemResponseFactory;
use App\Support\Http\ProblemType;
use Illuminate\Http\JsonResponse;

/**
 * RFC 7807 (Problem Details for HTTP APIs) helper trait.
 *
 * Provides unified error response formatting across all controllers.
 */
trait Problems
{
    /**
     * Generate a standardized RFC 7807 problem+json response.
     *
     * @param array<string, mixed> $extensions Additional problem-specific extension fields
     * @param array<string, string> $headers Extra headers to append to the response
     * @return JsonResponse
     */
    protected function problem(
        ProblemType $type,
        ?string $detail = null,
        array $extensions = [],
        array $headers = [],
        ?string $title = null,
        ?int $status = null,
        ?string $code = null,
    ): JsonResponse {
        return ProblemResponseFactory::make($type, $detail, $extensions, $headers, $title, $status, $code);
    }

    /**
     * Shorthand for 401 Unauthorized problem.
     *
     * @param string|null $detail
     * @param array<string, mixed> $extensions
     * @param array<string, string> $headers
     * @return JsonResponse
     */
    protected function unauthorized(?string $detail = null, array $extensions = [], array $headers = []): JsonResponse
    {
        return $this->problem(ProblemType::UNAUTHORIZED, $detail, $extensions, $headers);
    }

    /**
     * Shorthand for 403 Forbidden problem.
     *
     * @param string|null $detail
     * @param array<string, mixed> $extensions
     * @param array<string, string> $headers
     * @return JsonResponse
     */
    protected function forbidden(?string $detail = null, array $extensions = [], array $headers = []): JsonResponse
    {
        return $this->problem(ProblemType::FORBIDDEN, $detail, $extensions, $headers);
    }

    /**
     * Shorthand for 500 Internal Server Error problem.
     *
     * @param string $detail
     * @param array<string, mixed> $extensions
     * @return JsonResponse
     */
    protected function internalError(string $detail, array $extensions = []): JsonResponse
    {
        return $this->problem(ProblemType::INTERNAL_ERROR, $detail, $extensions);
    }

    /**
     * Shorthand for 429 Too Many Requests problem.
     *
     * @param string $detail
     * @param array<string, mixed> $extensions
     * @return JsonResponse
     */
    protected function tooManyRequests(string $detail, array $extensions = []): JsonResponse
    {
        return $this->problem(ProblemType::RATE_LIMIT_EXCEEDED, $detail, $extensions);
    }
}

