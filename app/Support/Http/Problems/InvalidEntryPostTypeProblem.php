<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

final class InvalidEntryPostTypeProblem extends ValidationProblem
{
    public function __construct()
    {
        parent::__construct(
            'The specified post type does not exist.',
            ['post_type' => ['The specified post type does not exist.']],
        );
    }
}
