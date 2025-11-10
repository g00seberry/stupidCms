<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class OptionNotFoundProblem extends HttpProblemException
{
    public function __construct(string $namespace, string $key)
    {
        parent::__construct(
            Problem::of(ProblemType::NOT_FOUND)
                ->detail(sprintf('Option "%s/%s" was not found.', $namespace, $key))
                ->title('Option not found')
                ->code('NOT_FOUND')
        );
    }
}
