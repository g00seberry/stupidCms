<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class PluginNotFoundProblem extends HttpProblemException
{
    public function __construct(string $slug)
    {
        parent::__construct(
            Problem::of(ProblemType::PLUGIN_NOT_FOUND)
                ->detail(sprintf('Plugin with slug "%s" was not found.', $slug))
                ->title('Plugin not found')
        );
    }
}
