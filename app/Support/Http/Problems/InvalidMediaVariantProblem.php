<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class InvalidMediaVariantProblem extends ProblemException
{
    public function __construct(string $detail)
    {
        parent::__construct(
            ProblemType::VALIDATION_ERROR,
            detail: $detail,
            title: 'Invalid variant',
        );
    }
}
