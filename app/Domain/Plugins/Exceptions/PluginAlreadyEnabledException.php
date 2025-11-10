<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Exceptions;

use App\Contracts\ProblemConvertible;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;
use RuntimeException;

final class PluginAlreadyEnabledException extends RuntimeException implements ProblemConvertible
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

    public function toProblem(): Problem
    {
        return Problem::of(ProblemType::PLUGIN_ALREADY_ENABLED)
            ->detail(sprintf('Plugin %s is already enabled.', $this->slug))
            ->extensions([
                'slug' => $this->slug,
            ]);
    }
}

