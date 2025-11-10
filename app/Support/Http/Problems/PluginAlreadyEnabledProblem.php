<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class PluginAlreadyEnabledProblem extends HttpProblemException
{
    public function __construct(string $message)
    {
        parent::__construct(
            Problem::of(ProblemType::PLUGIN_ALREADY_ENABLED)
                ->detail($message)
        );
    }
}
