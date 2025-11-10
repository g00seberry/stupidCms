<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class TaxonomyNotFoundProblem extends HttpProblemException
{
    public function __construct(string $slug)
    {
        parent::__construct(
            Problem::of(ProblemType::NOT_FOUND)
                ->detail(sprintf('Taxonomy with slug %s does not exist.', $slug))
                ->title('Taxonomy not found')
        );
    }
}
