<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

final class JwtAuthenticationException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $code,
        public readonly string $detail,
    ) {
        parent::__construct(sprintf('JWT authentication failed: %s (%s)', $reason, $detail));
    }
}
