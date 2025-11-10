<?php

declare(strict_types=1);

namespace App\Support\Http;

enum ProblemType: string
{
    case UNAUTHORIZED = 'https://stupidcms.dev/problems/unauthorized';
    case FORBIDDEN = 'https://stupidcms.dev/problems/forbidden';
    case NOT_FOUND = 'https://stupidcms.dev/problems/not-found';
    case VALIDATION_ERROR = 'https://stupidcms.dev/problems/validation-error';
    case CONFLICT = 'https://stupidcms.dev/problems/conflict';
    case SERVICE_UNAVAILABLE = 'https://stupidcms.dev/problems/service-unavailable';
    case INVALID_OPTION_IDENTIFIER = 'https://stupidcms.dev/problems/invalid-option-identifier';
    case INVALID_PLUGIN_MANIFEST = 'https://stupidcms.dev/problems/invalid-plugin-manifest';
    case MEDIA_DOWNLOAD_ERROR = 'https://stupidcms.dev/problems/media-download-error';
    case MEDIA_IN_USE = 'https://stupidcms.dev/problems/media-in-use';
    case MEDIA_VARIANT_ERROR = 'https://stupidcms.dev/problems/media-variant-error';
    case PLUGIN_ALREADY_DISABLED = 'https://stupidcms.dev/problems/plugin-already-disabled';
    case PLUGIN_ALREADY_ENABLED = 'https://stupidcms.dev/problems/plugin-already-enabled';
    case PLUGIN_NOT_FOUND = 'https://stupidcms.dev/problems/plugin-not-found';
    case ROUTES_RELOAD_FAILED = 'https://stupidcms.dev/problems/routes-reload-failed';
    case RATE_LIMIT_EXCEEDED = 'https://stupidcms.dev/problems/rate-limit-exceeded';
    case CSRF_MISMATCH = 'https://stupidcms.dev/problems/csrf-token-mismatch';
    case INTERNAL_ERROR = 'https://stupidcms.dev/problems/internal-error';

    public function status(): int
    {
        return match ($this) {
            self::UNAUTHORIZED => 401,
            self::FORBIDDEN => 403,
            self::NOT_FOUND => 404,
            self::VALIDATION_ERROR,
            self::INVALID_OPTION_IDENTIFIER,
            self::INVALID_PLUGIN_MANIFEST => 422,
            self::CONFLICT,
            self::MEDIA_IN_USE,
            self::PLUGIN_ALREADY_DISABLED,
            self::PLUGIN_ALREADY_ENABLED => 409,
            self::SERVICE_UNAVAILABLE => 503,
            self::MEDIA_DOWNLOAD_ERROR,
            self::MEDIA_VARIANT_ERROR,
            self::ROUTES_RELOAD_FAILED,
            self::INTERNAL_ERROR => 500,
            self::CSRF_MISMATCH => 419,
            self::PLUGIN_NOT_FOUND => 404,
            self::RATE_LIMIT_EXCEEDED => 429,
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::UNAUTHORIZED => 'Unauthorized',
            self::FORBIDDEN => 'Forbidden',
            self::NOT_FOUND => 'Not Found',
            self::VALIDATION_ERROR => 'Validation error',
            self::INVALID_OPTION_IDENTIFIER => 'Validation error',
            self::INVALID_PLUGIN_MANIFEST => 'Invalid plugin manifest',
            self::CONFLICT => 'Conflict',
            self::MEDIA_IN_USE => 'Media in use',
            self::PLUGIN_ALREADY_DISABLED => 'Plugin already disabled',
            self::PLUGIN_ALREADY_ENABLED => 'Plugin already enabled',
            self::PLUGIN_NOT_FOUND => 'Plugin not found',
            self::MEDIA_DOWNLOAD_ERROR => 'Failed to download media',
            self::MEDIA_VARIANT_ERROR => 'Failed to generate media variant',
            self::ROUTES_RELOAD_FAILED => 'Failed to reload plugin routes',
            self::SERVICE_UNAVAILABLE => 'Service Unavailable',
            self::RATE_LIMIT_EXCEEDED => 'Too Many Requests',
            self::INTERNAL_ERROR => 'Internal Server Error',
            self::CSRF_MISMATCH => 'CSRF Token Mismatch',
        };
    }

    public function defaultDetail(): string
    {
        return match ($this) {
            self::UNAUTHORIZED => 'Authentication is required to access this resource.',
            self::FORBIDDEN => 'Admin privileges are required.',
            self::NOT_FOUND => 'The requested resource was not found.',
            self::VALIDATION_ERROR => 'Validation failed.',
            self::INVALID_OPTION_IDENTIFIER => 'The provided option namespace/key is invalid.',
            self::INVALID_PLUGIN_MANIFEST => 'Plugin manifest is invalid.',
            self::CONFLICT => 'The request conflicts with the current state of the resource.',
            self::MEDIA_IN_USE => 'Media is referenced by content and cannot be deleted.',
            self::PLUGIN_ALREADY_DISABLED => 'Plugin is already disabled.',
            self::PLUGIN_ALREADY_ENABLED => 'Plugin is already enabled.',
            self::PLUGIN_NOT_FOUND => 'Plugin was not found.',
            self::MEDIA_DOWNLOAD_ERROR => 'Failed to generate download URL.',
            self::MEDIA_VARIANT_ERROR => 'Failed to generate media variant.',
            self::ROUTES_RELOAD_FAILED => 'Failed to reload plugin routes.',
            self::SERVICE_UNAVAILABLE => 'Service is temporarily unavailable.',
            self::RATE_LIMIT_EXCEEDED => 'Rate limit exceeded.',
            self::INTERNAL_ERROR => 'An unexpected error occurred.',
            self::CSRF_MISMATCH => 'CSRF token mismatch.',
        };
    }

    public function defaultCode(): ?string
    {
        return match ($this) {
            self::INVALID_OPTION_IDENTIFIER => 'INVALID_OPTION_IDENTIFIER',
            self::INVALID_PLUGIN_MANIFEST => 'INVALID_PLUGIN_MANIFEST',
            self::MEDIA_IN_USE => 'MEDIA_IN_USE',
            self::PLUGIN_ALREADY_DISABLED => 'PLUGIN_ALREADY_DISABLED',
            self::PLUGIN_ALREADY_ENABLED => 'PLUGIN_ALREADY_ENABLED',
            self::PLUGIN_NOT_FOUND => 'PLUGIN_NOT_FOUND',
            self::MEDIA_DOWNLOAD_ERROR => 'MEDIA_DOWNLOAD_ERROR',
            self::MEDIA_VARIANT_ERROR => 'MEDIA_VARIANT_ERROR',
            self::ROUTES_RELOAD_FAILED => 'ROUTES_RELOAD_FAILED',
            self::CSRF_MISMATCH => 'CSRF_TOKEN_MISMATCH',
            default => null,
        };
    }
}

