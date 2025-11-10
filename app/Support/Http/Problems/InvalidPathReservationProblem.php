<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

final class InvalidPathReservationProblem extends ValidationProblem
{
    public function __construct(string $message)
    {
        parent::__construct($message, []);
    }
}
