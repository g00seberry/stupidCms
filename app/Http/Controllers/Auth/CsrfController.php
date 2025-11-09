<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Resources\Admin\CsrfTokenResource;
use Illuminate\Support\Str;

final class CsrfController
{
    /**
     * Issue a CSRF token cookie.
     *
     * Returns a CSRF token in the response body and sets a non-HttpOnly cookie
     * so that JavaScript can read it and include it in subsequent requests.
     */
    public function issue(): CsrfTokenResource
    {
        $token = Str::random(40);

        return new CsrfTokenResource($token);
    }
}
