<?php

namespace App\Domain\Routing\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use Exception;

class PathAlreadyReservedException extends Exception implements ErrorConvertible
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

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::CONFLICT)
            ->detail($this->getMessage())
            ->meta([
                'path' => $this->path,
                'owner' => $this->owner,
            ])
            ->build();
    }
}

