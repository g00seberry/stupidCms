<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class TermNotFoundProblem extends ProblemException
{
    public function __construct(int $termId)
    {
        parent::__construct(
            ProblemType::NOT_FOUND,
            detail: sprintf('Term with ID %d does not exist.', $termId),
            title: 'Term not found',
        );
    }
}
