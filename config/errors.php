<?php

declare(strict_types=1);

use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use App\Support\Errors\HttpErrorException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

return [
    'kernel' => [
        'enabled' => env('ERROR_KERNEL_ENABLED', false),
    ],

    'types' => [
        ErrorCode::BAD_REQUEST->value => [
            'uri' => 'https://stupidcms.dev/problems/bad-request',
            'title' => 'Bad Request',
            'status' => 400,
            'detail' => 'The request could not be understood or was missing required parameters.',
        ],
        ErrorCode::UNAUTHORIZED->value => [
            'uri' => 'https://stupidcms.dev/problems/unauthorized',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => 'Authentication is required to access this resource.',
        ],
        ErrorCode::FORBIDDEN->value => [
            'uri' => 'https://stupidcms.dev/problems/forbidden',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => 'Admin privileges are required.',
        ],
        ErrorCode::NOT_FOUND->value => [
            'uri' => 'https://stupidcms.dev/problems/not-found',
            'title' => 'Not Found',
            'status' => 404,
            'detail' => 'The requested resource was not found.',
        ],
        ErrorCode::VALIDATION_ERROR->value => [
            'uri' => 'https://stupidcms.dev/problems/validation-error',
            'title' => 'Validation Error',
            'status' => 422,
            'detail' => 'Validation failed.',
        ],
        ErrorCode::CONFLICT->value => [
            'uri' => 'https://stupidcms.dev/problems/conflict',
            'title' => 'Conflict',
            'status' => 409,
            'detail' => 'The request conflicts with the current state of the resource.',
        ],
        ErrorCode::RATE_LIMIT_EXCEEDED->value => [
            'uri' => 'https://stupidcms.dev/problems/rate-limit-exceeded',
            'title' => 'Too Many Requests',
            'status' => 429,
            'detail' => 'Rate limit exceeded.',
        ],
        ErrorCode::SERVICE_UNAVAILABLE->value => [
            'uri' => 'https://stupidcms.dev/problems/service-unavailable',
            'title' => 'Service Unavailable',
            'status' => 503,
            'detail' => 'Service is temporarily unavailable.',
        ],
        ErrorCode::INTERNAL_SERVER_ERROR->value => [
            'uri' => 'https://stupidcms.dev/problems/internal-error',
            'title' => 'Internal Server Error',
            'status' => 500,
            'detail' => 'An unexpected error occurred.',
        ],
        ErrorCode::INVALID_OPTION_IDENTIFIER->value => [
            'uri' => 'https://stupidcms.dev/problems/invalid-option-identifier',
            'title' => 'Validation Error',
            'status' => 422,
            'detail' => 'The provided option namespace/key is invalid.',
        ],
        ErrorCode::INVALID_OPTION_PAYLOAD->value => [
            'uri' => 'https://stupidcms.dev/problems/invalid-option-payload',
            'title' => 'Validation Error',
            'status' => 422,
            'detail' => 'The provided option payload is invalid.',
        ],
        ErrorCode::INVALID_JSON_VALUE->value => [
            'uri' => 'https://stupidcms.dev/problems/invalid-json-value',
            'title' => 'Validation Error',
            'status' => 422,
            'detail' => 'The provided JSON value is invalid.',
        ],
        ErrorCode::INVALID_OPTION_FILTERS->value => [
            'uri' => 'https://stupidcms.dev/problems/invalid-option-filters',
            'title' => 'Validation Error',
            'status' => 422,
            'detail' => 'The provided option filters are invalid.',
        ],
        ErrorCode::INVALID_PLUGIN_MANIFEST->value => [
            'uri' => 'https://stupidcms.dev/problems/invalid-plugin-manifest',
            'title' => 'Invalid plugin manifest',
            'status' => 422,
            'detail' => 'Plugin manifest is invalid.',
        ],
        ErrorCode::PLUGIN_ALREADY_DISABLED->value => [
            'uri' => 'https://stupidcms.dev/problems/plugin-already-disabled',
            'title' => 'Plugin already disabled',
            'status' => 409,
            'detail' => 'Plugin is already disabled.',
        ],
        ErrorCode::PLUGIN_ALREADY_ENABLED->value => [
            'uri' => 'https://stupidcms.dev/problems/plugin-already-enabled',
            'title' => 'Plugin already enabled',
            'status' => 409,
            'detail' => 'Plugin is already enabled.',
        ],
        ErrorCode::PLUGIN_NOT_FOUND->value => [
            'uri' => 'https://stupidcms.dev/problems/plugin-not-found',
            'title' => 'Plugin not found',
            'status' => 404,
            'detail' => 'Plugin was not found.',
        ],
        ErrorCode::ROUTES_RELOAD_FAILED->value => [
            'uri' => 'https://stupidcms.dev/problems/routes-reload-failed',
            'title' => 'Failed to reload plugin routes',
            'status' => 500,
            'detail' => 'Failed to reload plugin routes.',
        ],
        ErrorCode::MEDIA_IN_USE->value => [
            'uri' => 'https://stupidcms.dev/problems/media-in-use',
            'title' => 'Media in use',
            'status' => 409,
            'detail' => 'Media is referenced by content and cannot be deleted.',
        ],
        ErrorCode::MEDIA_DOWNLOAD_ERROR->value => [
            'uri' => 'https://stupidcms.dev/problems/media-download-error',
            'title' => 'Failed to download media',
            'status' => 500,
            'detail' => 'Failed to generate download URL.',
        ],
        ErrorCode::MEDIA_VARIANT_ERROR->value => [
            'uri' => 'https://stupidcms.dev/problems/media-variant-error',
            'title' => 'Failed to generate media variant',
            'status' => 500,
            'detail' => 'Failed to generate media variant.',
        ],
        ErrorCode::CSRF_TOKEN_MISMATCH->value => [
            'uri' => 'https://stupidcms.dev/problems/csrf-token-mismatch',
            'title' => 'CSRF Token Mismatch',
            'status' => 419,
            'detail' => 'CSRF token mismatch.',
        ],
    ],

    'mappings' => [
        ValidationException::class => [
            'builder' => static function (ValidationException $exception, ErrorFactory $factory): ErrorPayload {
                $errors = $exception->errors();
                $detail = null;

                foreach ($errors as $messages) {
                    foreach ($messages as $message) {
                        if (is_string($message) && trim($message) !== '') {
                            $detail = $message;
                            break 2;
                        }
                    }
                }

                $detail ??= 'Validation failed.';

                return $factory->for(ErrorCode::VALIDATION_ERROR)
                    ->detail($detail)
                    ->meta(['errors' => $errors])
                    ->build();
            },
        ],
        AuthenticationException::class => [
            'builder' => static function (AuthenticationException $exception, ErrorFactory $factory): ErrorPayload {
                $detail = $exception->getMessage();

                if (trim($detail) === '') {
                    $detail = 'Authentication is required to access this resource.';
                }

                return $factory->for(ErrorCode::UNAUTHORIZED)
                    ->detail($detail)
                    ->build();
            },
        ],
        AuthorizationException::class => [
            'builder' => static function (AuthorizationException $exception, ErrorFactory $factory): ErrorPayload {
                $detail = $exception->getMessage();

                if (trim($detail) === '') {
                    $detail = 'Admin privileges are required.';
                }

                return $factory->for(ErrorCode::FORBIDDEN)
                    ->detail($detail)
                    ->build();
            },
        ],
        AccessDeniedHttpException::class => [
            'builder' => static function (AccessDeniedHttpException $exception, ErrorFactory $factory): ErrorPayload {
                $detail = $exception->getMessage();

                if (trim($detail) === '') {
                    $detail = 'Admin privileges are required.';
                }

               return $factory->for(ErrorCode::FORBIDDEN)
                    ->detail($detail)
                    ->build();
            },
        ],
        NotFoundHttpException::class => [
            'builder' => static function (NotFoundHttpException $exception, ErrorFactory $factory): ErrorPayload {
                $detail = $exception->getMessage();

                if (trim($detail) === '') {
                    $detail = 'The requested resource was not found.';
                }

                return $factory->for(ErrorCode::NOT_FOUND)
                    ->detail($detail)
                    ->build();
            },
        ],
        ThrottleRequestsException::class => [
            'builder' => static function (ThrottleRequestsException $exception, ErrorFactory $factory): ErrorPayload {
                $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;
                $builder = $factory->for(ErrorCode::RATE_LIMIT_EXCEEDED);

                if ($retryAfter !== null) {
                    $builder = $builder->addMeta('retry_after', $retryAfter);
                }

                return $builder->build();
            },
        ],
        QueryException::class => [
            'builder' => static function (QueryException $exception, ErrorFactory $factory): ErrorPayload {
                $previous = $exception->getPrevious();

                if ($previous instanceof HttpErrorException) {
                    return $previous->payload();
                }

                return $factory->for(ErrorCode::INTERNAL_SERVER_ERROR)
                    ->meta([
                        'sql' => $exception->getSql(),
                        'bindings' => $exception->getBindings(),
                    ])
                    ->build();
            },
            'report' => [
                'level' => 'error',
                'message' => 'Database error during API request',
                'context' => static fn (QueryException $exception, ErrorPayload $payload): array => [
                    'sql' => $exception->getSql(),
                    'bindings' => $exception->getBindings(),
                ],
            ],
        ],
        ServiceUnavailableHttpException::class => [
            'builder' => static function (ServiceUnavailableHttpException $exception, ErrorFactory $factory): ErrorPayload {
                $detail = $exception->getMessage();

                if (trim($detail) === '') {
                    $detail = 'Service is temporarily unavailable.';
                }

                $builder = $factory->for(ErrorCode::SERVICE_UNAVAILABLE)
                    ->detail($detail);

                if ($exception->getRetryAfter() !== null) {
                    $builder = $builder->addMeta('retry_after', $exception->getRetryAfter());
                }

                return $builder->build();
            },
            'report' => [
                'level' => 'error',
                'message' => 'Service unavailable during API request',
            ],
        ],
    ],

    'fallback' => [
        'builder' => static function (\Throwable $throwable, ErrorFactory $factory): ErrorPayload {
            return $factory->for(ErrorCode::INTERNAL_SERVER_ERROR)->build();
        },
        'report' => [
            'level' => 'error',
            'message' => 'Unhandled exception in API request',
        ],
    ],
];

