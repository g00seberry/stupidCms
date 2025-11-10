<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

abstract class ValidationProblem extends HttpProblemException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(string $detail, array $errors, ?string $code = null)
    {
        $problem = Problem::of(ProblemType::VALIDATION_ERROR)
            ->detail($detail)
            ->extensions($errors === [] ? [] : ['errors' => $errors]);

        if ($code !== null) {
            $problem = $problem->code($code);
        }

        parent::__construct($problem);
    }
}
