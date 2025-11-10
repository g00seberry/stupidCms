<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class AdminResponse
{
    private function __construct()
    {
    }

    public static function noContent(int $status = Response::HTTP_NO_CONTENT): Response
    {
        $response = response()->noContent($status);

        return AdminResponseHeaders::apply($response);
    }

    /**
     * @param  array<mixed>|null  $data
     */
    public static function json($data = null, int $status = Response::HTTP_OK, array $headers = [], int $options = 0): JsonResponse
    {
        /** @var JsonResponse $response */
        $response = response()->json($data, $status, $headers, $options);

        AdminResponseHeaders::apply($response);

        return $response;
    }
}

