<?php

namespace App\Domain\Routing\Exceptions;

use Exception;

class ForbiddenReservationRelease extends Exception
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
}

