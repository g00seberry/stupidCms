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
        public readonly string $detail,
    ) {
        parent::__construct(
            sprintf('JWT authentication failed: %s (%s)', $reason, $detail),
        );
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::UNAUTHORIZED)
            ->detail('Authentication is required to access this resource.')
            ->meta([
                'reason' => $this->reason,
                'detail' => $this->detail,
            ])
            ->build();
    }
}
