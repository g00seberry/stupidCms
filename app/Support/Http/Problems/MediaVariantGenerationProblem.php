<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class MediaVariantGenerationProblem extends HttpProblemException
{
    public function __construct(string $detail = 'Failed to generate media variant.')
    {
        parent::__construct(
            Problem::of(ProblemType::MEDIA_VARIANT_ERROR)
                ->detail($detail)
                ->title('Internal Server Error')
        );
    }
}
