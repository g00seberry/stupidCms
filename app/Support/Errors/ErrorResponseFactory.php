<?php

declare(strict_types=1);

namespace App\Support\Errors;

use App\Support\Http\AdminResponseHeaders;
use Illuminate\Http\JsonResponse;

final class ErrorResponseFactory
{
    public static function make(ErrorPayload $payload): JsonResponse
    {
        /** @var JsonResponse $response */
        $response = response()->json($payload->toArray(), $payload->status);
        $response->headers->set('Content-Type', 'application/problem+json');

        AdminResponseHeaders::apply($response);

        return $response;
    }
}

