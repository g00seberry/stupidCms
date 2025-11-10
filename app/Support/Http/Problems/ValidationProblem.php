<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

abstract class ValidationProblem extends ProblemException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(string $detail, array $errors, ?string $code = null)
    {
        parent::__construct(
            ProblemType::VALIDATION_ERROR,
            detail: $detail,
            extensions: $errors === [] ? [] : ['errors' => $errors],
            code: $code,
        );
    }
}
