<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Exceptions;

use App\Contracts\ProblemConvertible;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;
use RuntimeException;

final class InvalidPluginManifest extends RuntimeException implements ProblemConvertible
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

    public function toProblem(): Problem
    {
        return Problem::of(ProblemType::INVALID_PLUGIN_MANIFEST)
            ->detail($this->getMessage())
            ->extensions([
                'path' => $this->path,
                'reason' => $this->reason,
            ]);
    }
}

