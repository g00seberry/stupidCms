<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class TaxonomyNotFoundProblem extends ProblemException
{
    public function __construct(string $slug)
    {
        parent::__construct(
            ProblemType::NOT_FOUND,
            detail: sprintf('Taxonomy with slug %s does not exist.', $slug),
            title: 'Taxonomy not found',
        );
    }
}
