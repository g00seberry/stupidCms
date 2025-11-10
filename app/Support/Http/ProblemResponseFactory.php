<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Support\Problems\Problem;
use Illuminate\Http\JsonResponse;

final class ProblemResponseFactory
{
    public static function make(Problem $problem): JsonResponse
    {
        $payload = array_merge(
            [
                'type' => $problem->type()->value,
                'title' => $problem->getTitle() ?? $problem->type()->title(),
                'status' => $problem->getStatus() ?? $problem->type()->status(),
                'detail' => $problem->getDetail() ?? $problem->type()->defaultDetail(),
            ],
            self::codePayload($problem),
            $problem->getExtensions(),
        );

        /** @var JsonResponse $response */
        $response = response()->json($payload, $payload['status']);
        $response->headers->set('Content-Type', 'application/problem+json');

        foreach ($problem->getHeaders() as $name => $value) {
            $response->headers->set($name, $value);
        }

        $headersLower = array_change_key_case($problem->getHeaders(), CASE_LOWER);
        AdminResponseHeaders::apply($response, ! array_key_exists('cache-control', $headersLower));

        return $response;
    }

    private static function codePayload(Problem $problem): array
    {
        $code = $problem->getCode() ?? $problem->type()->defaultCode();

        return $code !== null ? ['code' => $code] : [];
    }
}
