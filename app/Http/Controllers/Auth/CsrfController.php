<?php

namespace App\Http\Controllers\Auth;

use App\Support\JwtCookies;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class CsrfController
{
    /**
     * Issue a CSRF token cookie.
     *
     * Returns a CSRF token in the response body and sets a non-HttpOnly cookie
     * so that JavaScript can read it and include it in subsequent requests.
     *
     * @return JsonResponse
     */
    public function issue(): JsonResponse
    {
        $token = Str::random(40);

        return response()->json(['csrf' => $token])
            ->withCookie(JwtCookies::csrf($token));
    }
}
