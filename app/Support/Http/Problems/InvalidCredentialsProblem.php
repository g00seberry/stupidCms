<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class InvalidCredentialsProblem extends HttpProblemException
{
    public function __construct()
    {
        parent::__construct(
            Problem::of(ProblemType::UNAUTHORIZED)
                ->detail('Invalid credentials.')
        );
    }
}
