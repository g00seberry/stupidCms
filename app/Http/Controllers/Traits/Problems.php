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
    protected function problem(int $status, string $title, string $detail, array $ext = [], array $headers = []): JsonResponse
    {
        $payload = array_merge([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], $ext);

        $response = response()->json($payload, $status);
        $response->headers->set('Content-Type', 'application/problem+json');

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * Render a standardized problem response using a preset array.
     *
     * @param array{type: string, title: string, detail: string, status: int} $preset
     * @param array<string, mixed> $ext
     * @param array<string, string> $headers
     */
    protected function problemFromPreset(array $preset, array $ext = [], array $headers = []): JsonResponse
    {
        $extWithType = array_merge(['type' => $preset['type']], $ext);

        return $this->problem(
            $preset['status'],
            $preset['title'],
            $preset['detail'],
            $extWithType,
            $headers
        );
    }

    /**
     * Shorthand for 401 Unauthorized problem using centralized preset.
     *
     * @param string|null $detail
     * @param array<string, mixed> $ext
     * @param array<string, string> $headers
     * @return JsonResponse
     */
    protected function unauthorized(?string $detail = null, array $ext = [], array $headers = []): JsonResponse
    {
        $preset = \App\Support\ProblemDetails::unauthorized($detail);
        return $this->problemFromPreset($preset, $ext, $headers);
    }

    /**
     * Shorthand for 403 Forbidden problem using centralized preset.
     *
     * @param string|null $detail
     * @param array<string, mixed> $ext
     * @param array<string, string> $headers
     * @return JsonResponse
     */
    protected function forbidden(?string $detail = null, array $ext = [], array $headers = []): JsonResponse
    {
        $preset = \App\Support\ProblemDetails::forbidden($detail);
        return $this->problemFromPreset($preset, $ext, $headers);
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

