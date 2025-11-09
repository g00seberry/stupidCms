<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\JwtService;
use App\Http\Controllers\Traits\Problems;
use App\Models\User;
use App\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Basic JWT authentication middleware.
 * 
 * Verifies JWT access token from cookie without requiring admin scope.
 * Use this for authenticated endpoints that don't require admin privileges.
 */
final class JwtAuth
{
    use Problems;

    private const GUARD = 'api';

    public function __construct(
        private JwtService $jwt
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * Verifies JWT access token from cookie and checks:
     * - Token is valid (signature, expiration)
     * - User exists in database
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $accessToken = (string) $request->cookie(config('jwt.cookies.access'), '');

        if ($accessToken === '') {
            return $this->respondUnauthorized('missing_token');
        }

        try {
            $verified = $this->jwt->verify($accessToken, 'access');
            $claims = $verified['claims'];
        } catch (\Throwable $e) {
            return $this->respondUnauthorized('invalid_token');
        }

        $subject = $claims['sub'] ?? null;
        if (! $this->isValidSubject($subject)) {
            return $this->respondUnauthorized('invalid_subject');
        }

        $userId = (int) $subject;
        $user = User::query()->find($userId);
        if (! $user) {
            return $this->respondUnauthorized('user_not_found');
        }

        Auth::shouldUse(self::GUARD);
        Auth::setUser($user);

        return $next($request);
    }

    private function respondUnauthorized(string $reason): JsonResponse
    {
        return $this->problemFromPreset(
            ProblemDetails::unauthorized(),
            headers: [
                'WWW-Authenticate' => 'Bearer',
                'Cache-Control' => 'no-store, private',
                'Vary' => 'Cookie',
            ]
        );
    }

    private function isValidSubject(mixed $subject): bool
    {
        if (! is_numeric($subject)) {
            return false;
        }

        $intVal = (int) $subject;
        
        return $intVal > 0 && (string) $intVal === trim((string) $subject);
    }
}

