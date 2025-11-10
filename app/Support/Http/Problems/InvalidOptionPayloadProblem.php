<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

final class InvalidOptionPayloadProblem extends ValidationProblem
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(array $errors, string $code)
    {
        parent::__construct('Invalid option payload.', $errors, $code);
    }
}
