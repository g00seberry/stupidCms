<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class MediaDownloadProblem extends HttpProblemException
{
    public function __construct(string $detail = 'Failed to generate download URL.')
    {
        parent::__construct(
            Problem::of(ProblemType::MEDIA_DOWNLOAD_ERROR)
                ->detail($detail)
                ->title('Internal Server Error')
        );
    }
}
