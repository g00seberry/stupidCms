<?php

namespace App\Domain\Routing\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use Exception;

class InvalidPathException extends Exception implements ErrorConvertible
{
    public function __construct(string $message = "Invalid path", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::VALIDATION_ERROR)
            ->detail($this->getMessage())
            ->build();
    }
}

