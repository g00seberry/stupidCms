<?php

namespace App\Http\Middleware;

use App\Domain\Auth\JwtService;
use App\Http\Controllers\Traits\Problems;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AdminAuth
{
    use Problems;

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
     * - User has 'admin' role in database
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $at = (string) $request->cookie(config('jwt.cookies.access'), '');

        if ($at === '') {
            return $this->unauthorized('Missing access token.');
        }

        try {
            $verified = $this->jwt->verify($at, 'access');
            $claims = $verified['claims']; // sub, scp, aud, exp
        } catch (\Throwable $e) {
            return $this->unauthorized('Invalid access token.');
        }

        // Require both audience and scope
        if (($claims['aud'] ?? 'api') !== 'admin' || !in_array('admin', (array) ($claims['scp'] ?? []), true)) {
            return $this->problem(403, 'Forbidden', 'Insufficient scope.');
        }

        // Optional: check role from database
        $user = User::find((int) $claims['sub']);
        if (! $user || ! $user->is_admin) {
            return $this->problem(403, 'Forbidden', 'Admin role required.');
        }

        // Set authenticated user for the request
        Auth::setUser($user);

        return $next($request);
    }
}

