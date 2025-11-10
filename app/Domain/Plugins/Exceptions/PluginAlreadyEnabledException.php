<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Exceptions;

use App\Contracts\ErrorConvertible;
use App\Support\Errors\ErrorCode;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorPayload;
use RuntimeException;

final class PluginAlreadyEnabledException extends RuntimeException implements ErrorConvertible
{
    private function __construct(
        public readonly string $slug,
        string $message
    ) {
        parent::__construct($message);
    }

    public static function forSlug(string $slug): self
    {
        return new self(
            $slug,
            sprintf('Plugin "%s" already enabled.', $slug)
        );
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::PLUGIN_ALREADY_ENABLED)
            ->detail(sprintf('Plugin %s is already enabled.', $this->slug))
            ->meta([
                'slug' => $this->slug,
            ])
            ->build();
    }
}

