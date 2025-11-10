<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Support\Problems\Problem;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class HttpProblemException extends RuntimeException
{
    public function __construct(private readonly Problem $problem)
    {
        parent::__construct($problem->userFriendlyDetail());
    }

    public function problem(): Problem
    {
        return $this->problem;
    }

    public function apply(JsonResponse $response): JsonResponse
    {
        return $this->configureResponse($response);
    }

    protected function configureResponse(JsonResponse $response): JsonResponse
    {
        return $response;
    }
}
