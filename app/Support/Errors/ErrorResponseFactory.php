<?php

declare(strict_types=1);

namespace App\Support\Errors;

use App\Support\Http\AdminResponseHeaders;
use Illuminate\Http\JsonResponse;

final class ErrorResponseFactory
{
    public static function make(ErrorPayload $payload): JsonResponse
    {
        $data = $payload->toArray();

        $meta = $data['meta'] ?? [];

        if (is_array($meta) && array_key_exists('errors', $meta) && is_array($meta['errors'])) {
            $data['errors'] = $meta['errors'];
        }

        /** @var JsonResponse $response */
        $response = response()->json($data, $payload->status);
        $response->headers->set('Content-Type', 'application/problem+json');

        AdminResponseHeaders::apply($response);

        return $response;
    }
}

