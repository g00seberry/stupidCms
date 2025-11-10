<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class InvalidMediaVariantProblem extends HttpProblemException
{
    public function __construct(string $detail)
    {
        parent::__construct(
            Problem::of(ProblemType::VALIDATION_ERROR)
                ->detail($detail)
                ->title('Invalid variant')
        );
    }
}
