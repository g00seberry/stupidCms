<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class ForbiddenReservationReleaseProblem extends ProblemException
{
    public function __construct(string $message, string $path, string $owner, string $attemptedSource)
    {
        parent::__construct(
            ProblemType::FORBIDDEN,
            detail: $message,
            extensions: [
                'path' => $path,
                'owner' => $owner,
                'attempted_source' => $attemptedSource,
            ],
        );
    }
}
