<?php

declare(strict_types=1);

namespace App\Support\Errors;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ErrorReporter
{
    private function __construct()
    {
    }

    public static function report(Throwable $throwable, ErrorPayload $payload, ?ErrorReportDefinition $definition): void
    {
        $level = $definition?->level ?? 'error';
        $message = $definition?->message ?? $payload->title;

        $context = [
            'exception' => $throwable,
            'error_code' => $payload->code->value,
            'error_type' => $payload->type,
            'status' => $payload->status,
            'detail' => $payload->detail,
            'meta' => $payload->meta(),
            'trace_id' => $payload->traceId,
            'request_id' => self::resolveRequestId(),
            'user_id' => self::resolveUserId(),
        ];

        $additional = $definition?->resolveContext($throwable, $payload) ?? [];

        if ($additional !== []) {
            $context = array_merge($context, $additional);
        }

        $context = array_filter(
            $context,
            static fn ($value) => $value !== null,
        );

        Log::log($level, $message, $context);
    }

    private static function resolveRequestId(): ?string
    {
        $request = self::request();

        if ($request === null) {
            return null;
        }

        foreach (['X-Request-ID', 'X-Request-Id', 'X-Correlation-ID', 'X-Correlation-Id'] as $header) {
            $value = $request->headers->get($header);

            if (is_array($value)) {
                $value = reset($value) ?: null;
            }

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $attributes = [
            'request_id',
            'requestId',
            'request-id',
            'X-Request-ID',
        ];

        foreach ($attributes as $attribute) {
            $value = $request->attributes->get($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function resolveUserId(): int|string|null
    {
        $request = self::request();

        if ($request !== null) {
            $resolver = $request->getUserResolver();

            try {
                $user = $resolver();
            } catch (Throwable) {
                $user = $resolver(null);
            }

            $identifier = self::identifier($user);

            if ($identifier !== null) {
                return $identifier;
            }

            $identifier = self::identifier($request->user());

            if ($identifier !== null) {
                return $identifier;
            }
        }

        return self::identifier(Auth::user());
    }

    private static function identifier(mixed $user): int|string|null
    {
        if (! $user instanceof Authenticatable) {
            return null;
        }

        $identifier = $user->getAuthIdentifier();

        if ($identifier === null) {
            return null;
        }

        if (is_int($identifier) || is_string($identifier)) {
            return $identifier;
        }

        return (string) $identifier;
    }

    private static function request(): ?Request
    {
        if (! App::bound('request')) {
            return null;
        }

        $request = App::make('request');

        return $request instanceof Request ? $request : null;
    }
}

