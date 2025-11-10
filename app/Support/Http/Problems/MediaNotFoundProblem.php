<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class MediaNotFoundProblem extends ProblemException
{
    public function __construct(string $mediaId, ?string $prefix = null)
    {
        $detail = $prefix ? sprintf('%s media with ID %s does not exist.', ucfirst($prefix), $mediaId) : sprintf('Media with ID %s does not exist.', $mediaId);

        parent::__construct(
            ProblemType::NOT_FOUND,
            detail: $detail,
            title: 'Media not found',
        );
    }
}
