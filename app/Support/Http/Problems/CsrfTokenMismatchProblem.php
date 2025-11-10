<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

final class CsrfTokenMismatchProblem extends HttpProblemException
{
    public function __construct(private readonly Cookie $cookie)
    {
        parent::__construct(
            Problem::of(ProblemType::CSRF_MISMATCH)
                ->headers(['Vary' => 'Origin'])
        );
    }

    protected function configureResponse(JsonResponse $response): JsonResponse
    {
        return $response->withCookie($this->cookie);
    }
}
