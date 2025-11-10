<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

final class RefreshTokenUnauthorizedProblem extends HttpProblemException
{
    /**
     * @param array<int, Cookie> $cookies
     */
    public function __construct(string $detail, private readonly array $cookies)
    {
        parent::__construct(
            Problem::of(ProblemType::UNAUTHORIZED)
                ->detail($detail)
        );
    }

    protected function configureResponse(JsonResponse $response): JsonResponse
    {
        foreach ($this->cookies as $cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
