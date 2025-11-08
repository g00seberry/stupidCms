<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralized RFC7807 problem definitions used across the API layer.
 */
final class ProblemDetails
{
    public const TYPE_UNAUTHORIZED = 'https://stupidcms.dev/problems/unauthorized';
    public const TYPE_FORBIDDEN = 'https://stupidcms.dev/problems/forbidden';
    public const TYPE_SERVICE_UNAVAILABLE = 'https://stupidcms.dev/problems/service-unavailable';

    public const TITLE_UNAUTHORIZED = 'Unauthorized';
    public const TITLE_FORBIDDEN = 'Forbidden';
    public const TITLE_SERVICE_UNAVAILABLE = 'Service Unavailable';

    public const DETAIL_UNAUTHORIZED = 'Authentication is required to access this resource.';
    public const DETAIL_FORBIDDEN = 'Admin privileges are required.';
    public const DETAIL_SERVICE_UNAVAILABLE = 'Search service is temporarily unavailable.';

    /**
     * @return array{type: string, title: string, detail: string, status: int}
     */
    public static function unauthorized(?string $detail = null): array
    {
        return [
            'type' => self::TYPE_UNAUTHORIZED,
            'title' => self::TITLE_UNAUTHORIZED,
            'status' => 401,
            'detail' => $detail ?? self::DETAIL_UNAUTHORIZED,
        ];
    }

    /**
     * @return array{type: string, title: string, detail: string, status: int}
     */
    public static function forbidden(?string $detail = null): array
    {
        return [
            'type' => self::TYPE_FORBIDDEN,
            'title' => self::TITLE_FORBIDDEN,
            'status' => 403,
            'detail' => $detail ?? self::DETAIL_FORBIDDEN,
        ];
    }

    /**
     * @return array{type: string, title: string, detail: string, status: int}
     */
    public static function serviceUnavailable(?string $detail = null): array
    {
        return [
            'type' => self::TYPE_SERVICE_UNAVAILABLE,
            'title' => self::TITLE_SERVICE_UNAVAILABLE,
            'status' => 503,
            'detail' => $detail ?? self::DETAIL_SERVICE_UNAVAILABLE,
        ];
    }
}


