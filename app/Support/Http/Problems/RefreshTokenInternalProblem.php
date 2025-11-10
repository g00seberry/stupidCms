<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class RefreshTokenInternalProblem extends HttpProblemException
{
    public function __construct()
    {
        parent::__construct(
            Problem::of(ProblemType::INTERNAL_ERROR)
                ->detail('Failed to refresh token due to server error.')
        );
    }
}
