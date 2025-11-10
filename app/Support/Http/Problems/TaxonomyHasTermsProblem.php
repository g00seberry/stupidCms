<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class TaxonomyHasTermsProblem extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            ProblemType::CONFLICT,
            detail: 'Cannot delete taxonomy while terms exist. Use force=1 to cascade delete.',
            title: 'Taxonomy has terms',
        );
    }
}
