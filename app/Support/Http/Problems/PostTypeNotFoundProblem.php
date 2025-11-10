<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class PostTypeNotFoundProblem extends HttpProblemException
{
    public function __construct(string $slug)
    {
        parent::__construct(
            Problem::of(ProblemType::NOT_FOUND)
                ->detail(sprintf('Unknown post type slug: %s', $slug))
                ->title('PostType not found')
        );
    }
}
