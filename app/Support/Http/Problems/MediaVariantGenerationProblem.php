<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class MediaVariantGenerationProblem extends ProblemException
{
    public function __construct(string $detail = 'Failed to generate media variant.')
    {
        parent::__construct(
            ProblemType::MEDIA_VARIANT_ERROR,
            detail: $detail,
            title: 'Internal Server Error',
        );
    }
}
