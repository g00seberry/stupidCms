<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class PluginNotFoundProblem extends ProblemException
{
    public function __construct(string $slug)
    {
        parent::__construct(
            ProblemType::PLUGIN_NOT_FOUND,
            detail: sprintf('Plugin with slug "%s" was not found.', $slug),
            title: 'Plugin not found',
        );
    }
}
