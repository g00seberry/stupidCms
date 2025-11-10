<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

final class MissingReservationPathProblem extends ValidationProblem
{
    public function __construct()
    {
        parent::__construct('Path is required either in URL parameter or request body.', []);
    }
}
