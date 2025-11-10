<?php

declare(strict_types=1);

namespace App\Support\Logging;

use App\Support\Http\ProblemType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProblemReporter
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function report(Throwable $throwable, ProblemType $type, string $message, array $context = []): void
    {
        $context = array_merge($context, [
            'exception' => $throwable,
            'problem_type' => $type->value,
            'request_id' => self::resolveRequestId(),
            'user_id' => self::resolveUserId(),
        ]);

        $context = array_filter($context, static fn ($value) => $value !== null);

        Log::error($message, $context);
    }

    private static function resolveRequestId(): ?string
    {
        $request = self::getRequest();

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

        foreach (['request_id', 'requestId', 'request-id'] as $attribute) {
            $value = $request->attributes->get($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function resolveUserId(): int|string|null
    {
        $request = self::getRequest();

        if ($request !== null) {
            $resolver = $request->getUserResolver();

            try {
                $user = $resolver();
            } catch (Throwable) {
                $user = $resolver(null);
            }

            $identifier = self::extractIdentifier($user);

            if ($identifier !== null) {
                return $identifier;
            }

            $identifier = self::extractIdentifier($request->user());

            if ($identifier !== null) {
                return $identifier;
            }
        }

        return self::extractIdentifier(Auth::user());
    }

    private static function extractIdentifier(mixed $user): int|string|null
    {
        if (! $user instanceof Authenticatable) {
            return null;
        }

        $identifier = $user->getAuthIdentifier();

        if ($identifier === null) {
            return null;
        }

        if (is_string($identifier) || is_int($identifier)) {
            return $identifier;
        }

        return (string) $identifier;
    }

    private static function getRequest(): ?Request
    {
        if (! App::bound('request')) {
            return null;
        }

        $request = App::make('request');

        return $request instanceof Request ? $request : null;
    }
}
