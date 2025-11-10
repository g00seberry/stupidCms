<?php

namespace App\Domain\Routing\Exceptions;

use App\Contracts\ProblemConvertible;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;
use Exception;

class InvalidPathException extends Exception implements ProblemConvertible
{
    public function __construct(string $message = "Invalid path", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function toProblem(): Problem
    {
        return Problem::of(ProblemType::VALIDATION_ERROR)
            ->detail($this->getMessage());
    }
}

