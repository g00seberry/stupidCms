<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use RuntimeException;

final class JwtAuthenticationException extends RuntimeException implements ErrorConvertible
{
    public function __construct(
        public readonly string $reason,
        public readonly string $errorCode,
        public readonly string $detail,
    ) {
        parent::__construct(
            sprintf('JWT authentication failed: %s (%s)', $reason, $detail),
        );
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        $code = ErrorCode::tryFrom($this->errorCode) ?? ErrorCode::UNAUTHORIZED;

        return $factory->for($code)
            ->detail('Authentication is required to access this resource.')
            ->meta([
                'reason' => $this->reason,
                'detail' => $this->detail,
            ])
            ->build();
    }
}
