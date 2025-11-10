<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class MediaDownloadProblem extends ProblemException
{
    public function __construct(string $detail = 'Failed to generate download URL.')
    {
        parent::__construct(
            ProblemType::MEDIA_DOWNLOAD_ERROR,
            detail: $detail,
            title: 'Internal Server Error',
        );
    }
}
