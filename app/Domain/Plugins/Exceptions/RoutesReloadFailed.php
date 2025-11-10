<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use RuntimeException;
use Throwable;

final class RoutesReloadFailed extends RuntimeException implements ErrorConvertible
{
    public static function from(Throwable $previous): self
    {
        return new self('Failed to reload plugin routes.', 0, $previous);
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::ROUTES_RELOAD_FAILED)
            ->detail($this->getMessage())
            ->build();
    }
}

