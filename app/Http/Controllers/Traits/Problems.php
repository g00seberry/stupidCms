<?php

declare(strict_types=1);

namespace App\Http\Controllers\Traits;

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
     * @param int $status HTTP status code
     * @param string $title Short, human-readable summary
     * @param string $detail Human-readable explanation specific to this occurrence
     * @param array<string, mixed> $ext Additional problem-specific extension fields
     * @return JsonResponse
     */
    protected function problem(int $status, string $title, string $detail, array $ext = []): JsonResponse
    {
        return response()->json(array_merge([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], $ext), $status)->header('Content-Type', 'application/problem+json');
    }

    /**
     * Shorthand for 401 Unauthorized problem.
     *
     * @param string $detail
     * @param array<string, mixed> $ext
     * @return JsonResponse
     */
    protected function unauthorized(string $detail, array $ext = []): JsonResponse
    {
        return $this->problem(401, 'Unauthorized', $detail, $ext);
    }

    /**
     * Shorthand for 500 Internal Server Error problem.
     *
     * @param string $detail
     * @param array<string, mixed> $ext
     * @return JsonResponse
     */
    protected function internalError(string $detail, array $ext = []): JsonResponse
    {
        return $this->problem(500, 'Internal Server Error', $detail, $ext);
    }

    /**
     * Shorthand for 429 Too Many Requests problem.
     *
     * @param string $detail
     * @param array<string, mixed> $ext
     * @return JsonResponse
     */
    protected function tooManyRequests(string $detail, array $ext = []): JsonResponse
    {
        return $this->problem(429, 'Too Many Requests', $detail, $ext);
    }
}

