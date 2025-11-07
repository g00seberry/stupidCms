<?php

namespace App\Domain\Routing\Exceptions;

use Exception;

class PathAlreadyReservedException extends Exception
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
}

