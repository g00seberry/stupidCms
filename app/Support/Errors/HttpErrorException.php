<?php

declare(strict_types=1);

namespace App\Support\Errors;

use Closure;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class HttpErrorException extends RuntimeException
{
    /**
     * @param Closure(JsonResponse):JsonResponse|null $responseConfigurator
     */
    public function __construct(
        private readonly ErrorPayload $payload,
        private readonly ?Closure $responseConfigurator = null,
    ) {
        parent::__construct($payload->detail);
    }

    public function payload(): ErrorPayload
    {
        return $this->payload;
    }

    public function apply(JsonResponse $response): JsonResponse
    {
        if ($this->responseConfigurator === null) {
            return $response;
        }

        $configurator = $this->responseConfigurator;

        return $configurator($response);
    }
}

