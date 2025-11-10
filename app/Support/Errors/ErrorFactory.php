<?php

declare(strict_types=1);

namespace App\Support\Errors;

final class ErrorFactory
{
    public function __construct(private readonly ErrorCatalog $catalog)
    {
    }

    public function for(ErrorCode $code): ErrorBuilder
    {
        return new ErrorBuilder($this->catalog->get($code));
    }

    public function catalog(): ErrorCatalog
    {
        return $this->catalog;
    }
}

