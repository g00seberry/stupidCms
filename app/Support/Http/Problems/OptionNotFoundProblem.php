<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class OptionNotFoundProblem extends ProblemException
{
    public function __construct(string $namespace, string $key)
    {
        parent::__construct(
            ProblemType::NOT_FOUND,
            detail: sprintf('Option "%s/%s" was not found.', $namespace, $key),
            title: 'Option not found',
            code: 'NOT_FOUND',
        );
    }
}
