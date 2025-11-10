<?php

namespace App\Domain\Routing\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use Exception;

class ForbiddenReservationRelease extends Exception implements ErrorConvertible
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

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::FORBIDDEN)
            ->detail($this->getMessage())
            ->meta([
                'path' => $this->path,
                'owner' => $this->owner,
                'attempted_source' => $this->attemptedSource,
            ])
            ->build();
    }
}

