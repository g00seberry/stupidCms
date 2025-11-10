<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;

interface ErrorConvertible
{
    public function toError(ErrorFactory $factory): ErrorPayload;
}

