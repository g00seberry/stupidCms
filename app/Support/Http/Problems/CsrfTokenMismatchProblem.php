<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

final class CsrfTokenMismatchProblem extends ProblemException
{
    public function __construct(private readonly Cookie $cookie)
    {
        parent::__construct(
            ProblemType::CSRF_MISMATCH,
            headers: ['Vary' => 'Origin'],
        );
    }

    protected function configureResponse(JsonResponse $response): JsonResponse
    {
        return $response->withCookie($this->cookie);
    }
}
