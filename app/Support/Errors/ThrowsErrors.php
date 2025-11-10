<?php

declare(strict_types=1);

namespace App\Support\Errors;

use Illuminate\Http\JsonResponse;

trait ThrowsErrors
{
    protected function errorFactory(): ErrorFactory
    {
        return app(ErrorFactory::class);
    }

    /**
     * @param array<string, mixed> $meta
     * @param callable(JsonResponse):JsonResponse|null $responseConfigurator
     */
    protected function throwError(
        ErrorCode $code,
        ?string $detail = null,
        array $meta = [],
        ?callable $responseConfigurator = null,
    ): never {
        $builder = $this->errorFactory()->for($code);

        if ($detail !== null) {
            $builder = $builder->detail($detail);
        }

        if ($meta !== []) {
            $builder = $builder->meta($meta);
        }

        throw new HttpErrorException($builder->build(), $responseConfigurator);
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, string> $headers
     */
    protected function throwErrorWithHeaders(
        ErrorCode $code,
        ?string $detail = null,
        array $meta = [],
        array $headers = [],
    ): never {
        $configurator = $headers === []
            ? null
            : static function (JsonResponse $response) use ($headers): JsonResponse {
                foreach ($headers as $name => $value) {
                    $response->headers->set($name, $value);
                }

                return $response;
            };

        $this->throwError($code, $detail, $meta, $configurator);
    }

    /**
     * @param array<string, mixed> $meta
     */
    protected function unauthorized(?string $detail = null, array $meta = [], array $headers = []): never
    {
        $headers = ['WWW-Authenticate' => 'Bearer', ...$headers];

        $this->throwErrorWithHeaders(ErrorCode::UNAUTHORIZED, $detail, $meta, $headers);
    }

    /**
     * @param array<string, mixed> $meta
     */
    protected function forbidden(?string $detail = null, array $meta = []): never
    {
        $this->throwError(ErrorCode::FORBIDDEN, $detail, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    protected function internalError(string $detail, array $meta = []): never
    {
        $this->throwError(ErrorCode::INTERNAL_SERVER_ERROR, $detail, $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    protected function tooManyRequests(string $detail, array $meta = []): never
    {
        $this->throwError(ErrorCode::RATE_LIMIT_EXCEEDED, $detail, $meta);
    }
}

