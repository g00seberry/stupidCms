<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Problems\Problem;

interface ProblemConvertible
{
    public function toProblem(): Problem;
}
