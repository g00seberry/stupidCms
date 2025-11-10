<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class TaxonomyHasTermsProblem extends HttpProblemException
{
    public function __construct()
    {
        parent::__construct(
            Problem::of(ProblemType::CONFLICT)
                ->detail('Cannot delete taxonomy while terms exist. Use force=1 to cascade delete.')
                ->title('Taxonomy has terms')
        );
    }
}
