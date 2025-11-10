<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Exceptions;

use App\Contracts\ProblemConvertible;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;
use RuntimeException;
use Throwable;

final class RoutesReloadFailed extends RuntimeException implements ProblemConvertible
{
    public static function from(Throwable $previous): self
    {
        return new self('Failed to reload plugin routes.', 0, $previous);
    }

    public function toProblem(): Problem
    {
        return Problem::of(ProblemType::ROUTES_RELOAD_FAILED)
            ->detail($this->getMessage());
    }
}

