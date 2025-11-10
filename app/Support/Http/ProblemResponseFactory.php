<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;

final class ProblemResponseFactory
{
    /**
     * @param array<string, mixed> $extensions
     * @param array<string, string> $headers
     */
    public static function make(
        ProblemType $type,
        ?string $detail = null,
        array $extensions = [],
        array $headers = [],
        ?string $title = null,
        ?int $status = null,
        ?string $code = null,
    ): JsonResponse {
        $payload = array_merge(
            [
                'type' => $type->value,
                'title' => $title ?? $type->title(),
                'status' => $status ?? $type->status(),
                'detail' => $detail ?? $type->defaultDetail(),
            ],
            self::codePayload($type, $code),
            $extensions,
        );

        /** @var JsonResponse $response */
        $response = response()->json($payload, $payload['status']);
        $response->headers->set('Content-Type', 'application/problem+json');

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        $headersLower = array_change_key_case($headers, CASE_LOWER);
        AdminResponseHeaders::apply($response, ! array_key_exists('cache-control', $headersLower));

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private static function codePayload(ProblemType $type, ?string $override): array
    {
        $code = $override ?? $type->defaultCode();

        return $code !== null ? ['code' => $code] : [];
    }
}

