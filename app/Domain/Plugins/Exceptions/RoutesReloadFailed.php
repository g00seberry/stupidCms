<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Exceptions;

use RuntimeException;
use Throwable;

final class RoutesReloadFailed extends RuntimeException
{
    public static function from(Throwable $previous): self
    {
        return new self('Failed to reload plugin routes.', 0, $previous);
    }
}

