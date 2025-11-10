<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class TermNotFoundProblem extends HttpProblemException
{
    public function __construct(int $termId)
    {
        parent::__construct(
            Problem::of(ProblemType::NOT_FOUND)
                ->detail(sprintf('Term with ID %d does not exist.', $termId))
                ->title('Term not found')
        );
    }
}
