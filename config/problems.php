<?php

declare(strict_types=1);

use App\Support\Http\ProblemDetailResolver;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

return [
    'handlers' => [
        ValidationException::class => [
            'factory' => static function (ValidationException $exception): Problem {
                $errors = $exception->errors();
                $firstError = collect($errors)->flatten()->filter()->first();

                return Problem::of(ProblemType::VALIDATION_ERROR)
                    ->detail($firstError ?? ProblemType::VALIDATION_ERROR->defaultDetail())
                    ->extensions(['errors' => $errors]);
            },
            'status' => ProblemType::VALIDATION_ERROR->status(),
        ],
        AuthenticationException::class => [
            'factory' => static function (AuthenticationException $exception): Problem {
                return Problem::of(ProblemType::UNAUTHORIZED)
                    ->detail($exception->getMessage() ?: ProblemType::UNAUTHORIZED->defaultDetail());
            },
            'status' => ProblemType::UNAUTHORIZED->status(),
        ],
        AuthorizationException::class => [
            'factory' => static function (AuthorizationException $exception): Problem {
                return Problem::of(ProblemType::FORBIDDEN)
                    ->detail($exception->getMessage() ?: ProblemType::FORBIDDEN->defaultDetail());
            },
            'status' => ProblemType::FORBIDDEN->status(),
        ],
        AccessDeniedHttpException::class => [
            'factory' => static function (AccessDeniedHttpException $exception): Problem {
                return Problem::of(ProblemType::FORBIDDEN)
                    ->detail($exception->getMessage() ?: ProblemType::FORBIDDEN->defaultDetail());
            },
            'status' => ProblemType::FORBIDDEN->status(),
        ],
        NotFoundHttpException::class => [
            'factory' => static fn (NotFoundHttpException $exception): Problem => Problem::of(ProblemType::NOT_FOUND)
                ->detail('The requested resource was not found.'),
            'status' => ProblemType::NOT_FOUND->status(),
        ],
        ThrottleRequestsException::class => [
            'factory' => static fn (ThrottleRequestsException $exception): Problem => Problem::of(ProblemType::RATE_LIMIT_EXCEEDED)
                ->detail('Rate limit exceeded.'),
            'status' => ProblemType::RATE_LIMIT_EXCEEDED->status(),
        ],
        QueryException::class => [
            'factory' => static fn (QueryException $exception): Problem => Problem::of(ProblemType::INTERNAL_ERROR)
                ->detail(ProblemDetailResolver::resolve($exception, ProblemType::INTERNAL_ERROR)),
            'status' => ProblemType::INTERNAL_ERROR->status(),
            'report' => [
                'type' => ProblemType::INTERNAL_ERROR,
                'message' => 'Database error during API request',
                'context' => static fn (QueryException $exception): array => [
                    'sql' => $exception->getSql(),
                    'bindings' => $exception->getBindings(),
                ],
            ],
        ],
        ServiceUnavailableHttpException::class => [
            'factory' => static fn (ServiceUnavailableHttpException $exception): Problem => Problem::of(ProblemType::SERVICE_UNAVAILABLE)
                ->detail(ProblemDetailResolver::resolve($exception, ProblemType::SERVICE_UNAVAILABLE, allowMessage: true)),
            'status' => ProblemType::SERVICE_UNAVAILABLE->status(),
            'report' => [
                'type' => ProblemType::SERVICE_UNAVAILABLE,
                'message' => 'Service unavailable during API request',
            ],
        ],
    ],
];
