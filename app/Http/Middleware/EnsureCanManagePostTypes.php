<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Ensures the authenticated user has the 'manage.posttypes' ability.
 * 
 * This middleware throws AuthorizationException which is caught by the global
 * exception handler (bootstrap/app.php) and rendered as RFC7807 problem+json.
 */
final class EnsureCanManagePostTypes
{
    public function handle(Request $request, Closure $next)
    {
        Gate::authorize('manage.posttypes');
        
        return $next($request);
    }
}

