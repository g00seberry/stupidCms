<?php

declare(strict_types=1);

namespace App\Support\Http;

use Symfony\Component\HttpFoundation\Response;

final class AdminResponseHeaders
{
    public const CACHE_CONTROL_VALUE = 'no-store, private';
    public const VARY_HEADER = 'Vary';
    public const VARY_COOKIE = 'Cookie';

    private function __construct()
    {
    }

    public static function apply(Response $response, bool $force = true): Response
    {
        if ($force || ! $response->headers->has('Cache-Control')) {
            $response->headers->set('Cache-Control', self::CACHE_CONTROL_VALUE);
        }

        $existing = $response->headers->get(self::VARY_HEADER, '');
        $parts = array_values(array_filter(array_map('trim', explode(',', $existing))));

        if (!in_array(self::VARY_COOKIE, $parts, true)) {
            $parts[] = self::VARY_COOKIE;
        }

        $response->headers->set(self::VARY_HEADER, implode(', ', $parts));

        return $response;
    }
}

