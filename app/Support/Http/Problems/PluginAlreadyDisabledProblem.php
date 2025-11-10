<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class PluginAlreadyDisabledProblem extends ProblemException
{
    public function __construct(string $message)
    {
        parent::__construct(ProblemType::PLUGIN_ALREADY_DISABLED, detail: $message);
    }
}
