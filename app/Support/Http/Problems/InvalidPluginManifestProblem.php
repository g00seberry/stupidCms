<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class InvalidPluginManifestProblem extends ProblemException
{
    public function __construct(string $message)
    {
        parent::__construct(ProblemType::INVALID_PLUGIN_MANIFEST, detail: $message);
    }
}
