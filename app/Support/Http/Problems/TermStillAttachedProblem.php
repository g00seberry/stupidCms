<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class TermStillAttachedProblem extends HttpProblemException
{
    public function __construct()
    {
        parent::__construct(
            Problem::of(ProblemType::CONFLICT)
                ->detail('Cannot delete term while it is attached to entries. Use forceDetach=1 to detach automatically.')
                ->title('Term still attached')
        );
    }
}
