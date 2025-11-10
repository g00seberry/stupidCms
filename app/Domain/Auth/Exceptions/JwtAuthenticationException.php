<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use App\Contracts\ProblemConvertible;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;
use RuntimeException;

final class JwtAuthenticationException extends RuntimeException implements ProblemConvertible
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

    public function toProblem(): Problem
    {
        return Problem::of(ProblemType::UNAUTHORIZED)
            ->detail(ProblemType::UNAUTHORIZED->defaultDetail())
            ->code($this->errorCode)
            ->headers([
                'WWW-Authenticate' => 'Bearer',
                'Pragma' => 'no-cache',
            ]);
    }
}
