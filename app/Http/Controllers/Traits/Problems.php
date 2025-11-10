<?php

declare(strict_types=1);

namespace App\Http\Controllers\Traits;

use App\Support\Http\ProblemResponseFactory;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;
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
        $problem = Problem::of($type);

        if ($detail !== null) {
            $problem = $problem->detail($detail);
        }

        if ($extensions !== []) {
            $problem = $problem->extensions($extensions);
        }

        if ($headers !== []) {
            $problem = $problem->headers($headers);
        }

        if ($title !== null) {
            $problem = $problem->title($title);
        }

        if ($status !== null) {
            $problem = $problem->status($status);
        }

        if ($code !== null) {
            $problem = $problem->code($code);
        }

        return ProblemResponseFactory::make($problem);
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

