<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\Exceptions\JwtAuthenticationException;
use App\Domain\Auth\JwtService;
use App\Models\User;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ThrowsErrors;
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
    use ThrowsErrors;

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
            $this->respondUnauthorized('missing_token');
        }

        try {
            $verified = $this->jwt->verify($accessToken, 'access');
            $claims = $verified['claims'];
        } catch (\Throwable $e) {
            $this->respondUnauthorized('invalid_token');
        }

        $subject = $claims['sub'] ?? null;
        if (! $this->isValidSubject($subject)) {
            $this->respondUnauthorized('invalid_subject');
        }

        $userId = (int) $subject;
        $user = User::query()->find($userId);
        if (! $user) {
            $this->respondUnauthorized('user_not_found');
        }

        Auth::shouldUse(self::GUARD);
        Auth::setUser($user);

        return $next($request);
    }

    /**
     * @var array<string, array{code: string, log_detail: string}>
     */
    private const FAILURE_RESPONSES = [
        'missing_token' => [
            'code' => 'JWT_ACCESS_TOKEN_MISSING',
            'log_detail' => 'Access token cookie is missing.',
        ],
        'invalid_token' => [
            'code' => 'JWT_ACCESS_TOKEN_INVALID',
            'log_detail' => 'Access token is invalid.',
        ],
        'invalid_subject' => [
            'code' => 'JWT_SUBJECT_INVALID',
            'log_detail' => 'Token subject claim is invalid.',
        ],
        'user_not_found' => [
            'code' => 'JWT_USER_NOT_FOUND',
            'log_detail' => 'Authenticated user was not found.',
        ],
    ];

    private function respondUnauthorized(string $reason): never
    {
        $response = self::FAILURE_RESPONSES[$reason] ?? [
            'code' => 'JWT_AUTH_FAILURE',
            'log_detail' => 'Unknown JWT authentication failure.',
        ];

        $code = ErrorCode::tryFrom($response['code']) ?? ErrorCode::JWT_AUTH_FAILURE;

        report(new JwtAuthenticationException($reason, $response['code'], $response['log_detail']));

        $this->throwErrorWithHeaders(
            $code,
            detail: null,
            meta: [
                'reason' => $reason,
                'message' => $response['log_detail'],
            ],
            headers: [
                'WWW-Authenticate' => 'Bearer',
                'Pragma' => 'no-cache',
            ],
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

