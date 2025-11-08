<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Exceptions;

use RuntimeException;

final class PluginAlreadyEnabledException extends RuntimeException
{
    public static function forSlug(string $slug): self
    {
        return new self(sprintf('Plugin "%s" already enabled.', $slug));
    }
}

