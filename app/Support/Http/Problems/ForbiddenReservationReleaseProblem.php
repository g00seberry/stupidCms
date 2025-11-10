<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class ForbiddenReservationReleaseProblem extends HttpProblemException
{
    public function __construct(string $message, string $path, string $owner, string $attemptedSource)
    {
        parent::__construct(
            Problem::of(ProblemType::FORBIDDEN)
                ->detail($message)
                ->extensions([
                    'path' => $path,
                    'owner' => $owner,
                    'attempted_source' => $attemptedSource,
                ])
        );
    }
}
