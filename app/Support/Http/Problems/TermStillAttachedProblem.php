<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class TermStillAttachedProblem extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            ProblemType::CONFLICT,
            detail: 'Cannot delete term while it is attached to entries. Use forceDetach=1 to detach automatically.',
            title: 'Term still attached',
        );
    }
}
