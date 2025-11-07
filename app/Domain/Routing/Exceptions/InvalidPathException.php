<?php

namespace App\Domain\Routing\Exceptions;

use Exception;

class InvalidPathException extends Exception
{
    public function __construct(string $message = "Invalid path", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

