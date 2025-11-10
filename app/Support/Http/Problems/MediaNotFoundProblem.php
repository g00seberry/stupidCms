<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class MediaNotFoundProblem extends HttpProblemException
{
    public function __construct(string $mediaId, ?string $prefix = null)
    {
        $detail = $prefix ? sprintf('%s media with ID %s does not exist.', ucfirst($prefix), $mediaId) : sprintf('Media with ID %s does not exist.', $mediaId);

        parent::__construct(
            Problem::of(ProblemType::NOT_FOUND)
                ->detail($detail)
                ->title('Media not found')
        );
    }
}
