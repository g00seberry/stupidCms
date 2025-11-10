<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

final class InvalidOptionFiltersProblem extends ValidationProblem
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(array $errors, string $code = 'INVALID_OPTION_FILTERS')
    {
        parent::__construct('Invalid option filter parameters.', $errors, $code);
    }
}
