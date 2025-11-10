<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class MissingMediaFileProblem extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            ProblemType::VALIDATION_ERROR,
            detail: 'File payload is missing.',
            extensions: [
                'errors' => [
                    'file' => ['File payload is required.'],
                ],
            ],
        );
    }
}
