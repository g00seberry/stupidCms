<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class MissingMediaFileProblem extends HttpProblemException
{
    public function __construct()
    {
        parent::__construct(
            Problem::of(ProblemType::VALIDATION_ERROR)
                ->detail('File payload is missing.')
                ->extensions([
                    'errors' => [
                        'file' => ['File payload is required.'],
                    ],
                ])
        );
    }
}
