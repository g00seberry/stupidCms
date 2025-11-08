<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Exceptions;

use RuntimeException;

final class InvalidPluginManifest extends RuntimeException
{
    public static function forPath(string $path, string $reason): self
    {
        return new self(sprintf('Invalid plugin manifest at "%s": %s', $path, $reason));
    }
}

