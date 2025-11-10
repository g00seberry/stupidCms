<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class RefreshTokenInternalProblem extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            ProblemType::INTERNAL_ERROR,
            detail: 'Failed to refresh token due to server error.',
        );
    }
}
