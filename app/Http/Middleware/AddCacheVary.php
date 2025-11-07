<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Adds Vary: Origin, Cookie headers to responses with cookies.
 *
 * This ensures proper cache behavior when cookies are present,
 * as responses with cookies may vary based on Origin and Cookie headers.
 */
final class AddCacheVary
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Add Vary headers for responses that set cookies
        if ($response->headers->has('Set-Cookie')) {
            $existingVary = $response->headers->get('Vary', '');
            $varyHeaders = array_filter(explode(',', $existingVary));
            $varyHeaders = array_map('trim', $varyHeaders);

            // Add Origin and Cookie if not already present
            if (!in_array('Origin', $varyHeaders, true)) {
                $varyHeaders[] = 'Origin';
            }
            if (!in_array('Cookie', $varyHeaders, true)) {
                $varyHeaders[] = 'Cookie';
            }

            $response->header('Vary', implode(', ', $varyHeaders));
        }

        return $response;
    }
}

