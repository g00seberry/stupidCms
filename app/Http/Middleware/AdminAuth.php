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
use Illuminate\Support\Facades\Log;

final class AdminAuth
{
    use Problems;

    private const REALM = 'admin';
    private const GUARD = 'admin';

    public function __construct(
        private JwtService $jwt
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * Verifies JWT access token from cookie and checks:
     * - Token is valid (signature, expiration)
     * - Audience (aud) is 'admin'
     * - Scope (scp) includes 'admin'
     * - User exists in database and is attached to admin guard
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $accessToken = (string) $request->cookie(config('jwt.cookies.access'), '');

        if ($accessToken === '') {
            return $this->respondUnauthorized($request, 'missing_token');
        }

        try {
            $verified = $this->jwt->verify($accessToken, 'access');
            $claims = $verified['claims'];
        } catch (\Throwable $e) {
            return $this->respondUnauthorized($request, 'invalid_token', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        if (($claims['aud'] ?? null) !== 'admin') {
            return $this->respondForbidden($request, 'invalid_audience');
        }

        $scopes = $claims['scp'] ?? [];
        if (! is_array($scopes)) {
            $scopes = (array) $scopes;
        }

        if (! in_array('admin', $scopes, true)) {
            return $this->respondForbidden($request, 'missing_scope');
        }

        $subject = $claims['sub'] ?? null;
        if (! $this->isValidSubject($subject)) {
            return $this->respondUnauthorized($request, 'invalid_subject', [
                'sub' => $subject,
            ]);
        }

        $userId = (int) $subject;
        $user = User::query()->find($userId);
        if (! $user) {
            return $this->respondUnauthorized($request, 'user_not_found', [
                'user_id' => $userId,
            ]);
        }

        Auth::shouldUse(self::GUARD);
        Auth::setUser($user);

        return $next($request);
    }

    private function respondUnauthorized(Request $request, string $reason, array $context = []): JsonResponse
    {
        $this->logFailure(401, $reason, $request, $context);

        return $this->problemFromPreset(
            ProblemDetails::unauthorized(),
            headers: [
                'WWW-Authenticate' => sprintf('Bearer realm="%s"', self::REALM),
                'Cache-Control' => 'no-store, private',
                'Pragma' => 'no-cache',
                'Vary' => 'Cookie',
            ]
        );
    }

    private function respondForbidden(Request $request, string $reason, array $context = []): JsonResponse
    {
        $this->logFailure(403, $reason, $request, $context);

        return $this->problemFromPreset(
            ProblemDetails::forbidden(),
            headers: [
                'Cache-Control' => 'no-store, private',
                'Vary' => 'Cookie',
            ]
        );
    }

    private function logFailure(int $status, string $reason, Request $request, array $context = []): void
    {
        $level = $status === 401 ? 'warning' : 'notice';
        
        $logContext = [
            'status' => $status,
            'reason' => $reason,
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Remove sensitive exception details in production
        if (isset($context['exception'])) {
            $logContext['exception_class'] = $context['exception'];
            unset($context['exception'], $context['message']);
        }

        Log::log($level, sprintf('[AdminAuth] %s: %s', $status, $reason), array_merge($logContext, $context));
    }

    private function isValidSubject(mixed $subject): bool
    {
        if (! is_int($subject) && ! is_string($subject)) {
            return false;
        }

        if (is_string($subject)) {
            $subject = trim($subject);
            if ($subject === '' || ! ctype_digit($subject)) {
                return false;
            }

            $subject = (int) $subject;
        }

        return is_int($subject) && $subject > 0;
    }
}

