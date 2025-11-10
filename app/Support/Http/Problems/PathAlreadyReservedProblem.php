<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class PathAlreadyReservedProblem extends ProblemException
{
    public function __construct(string $message, string $path, string $owner)
    {
        parent::__construct(
            ProblemType::CONFLICT,
            detail: $message,
            extensions: [
                'path' => $path,
                'owner' => $owner,
            ],
        );
    }
}
