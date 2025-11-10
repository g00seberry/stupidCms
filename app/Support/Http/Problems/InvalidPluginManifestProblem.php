<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class InvalidPluginManifestProblem extends HttpProblemException
{
    public function __construct(string $message)
    {
        parent::__construct(
            Problem::of(ProblemType::INVALID_PLUGIN_MANIFEST)
                ->detail($message)
        );
    }
}
