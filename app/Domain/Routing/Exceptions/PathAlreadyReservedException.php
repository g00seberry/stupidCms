<?php

namespace App\Domain\Routing\Exceptions;

use App\Contracts\ProblemConvertible;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;
use Exception;

class PathAlreadyReservedException extends Exception implements ProblemConvertible
{
    public function __construct(
        public readonly string $path,
        public readonly string $owner,
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        if ($message === "") {
            $message = "Path '{$path}' is already reserved by '{$owner}'";
        }
        parent::__construct($message, $code, $previous);
    }

    public function toProblem(): Problem
    {
        return Problem::of(ProblemType::CONFLICT)
            ->detail($this->getMessage())
            ->extensions([
                'path' => $this->path,
                'owner' => $this->owner,
            ]);
    }
}

