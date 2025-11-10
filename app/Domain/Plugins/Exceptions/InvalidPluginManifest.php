<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use RuntimeException;

final class InvalidPluginManifest extends RuntimeException implements ErrorConvertible
{
    private function __construct(
        public readonly string $path,
        public readonly string $reason,
        string $message
    ) {
        parent::__construct($message);
    }

    public static function forPath(string $path, string $reason): self
    {
        return new self(
            $path,
            $reason,
            sprintf('Invalid plugin manifest at "%s": %s', $path, $reason)
        );
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::INVALID_PLUGIN_MANIFEST)
            ->detail($this->getMessage())
            ->meta([
                'path' => $this->path,
                'reason' => $this->reason,
            ])
            ->build();
    }
}

