<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

final class InvalidMediaPayloadProblem extends ValidationProblem
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(array $errors)
    {
        parent::__construct('The media payload failed validation constraints.', $errors);
    }
}
