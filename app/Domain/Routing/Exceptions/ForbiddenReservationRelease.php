<?php

namespace App\Domain\Routing\Exceptions;

use App\Contracts\ProblemConvertible;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;
use Exception;

class ForbiddenReservationRelease extends Exception implements ProblemConvertible
{
    public function __construct(
        public readonly string $path,
        public readonly string $owner,
        public readonly string $attemptedSource,
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        if ($message === "") {
            $message = "Cannot release path '{$path}' reserved by '{$owner}' (attempted by '{$attemptedSource}')";
        }
        parent::__construct($message, $code, $previous);
    }

    public function toProblem(): Problem
    {
        return Problem::of(ProblemType::FORBIDDEN)
            ->detail($this->getMessage())
            ->extensions([
                'path' => $this->path,
                'owner' => $this->owner,
                'attempted_source' => $this->attemptedSource,
            ]);
    }
}

