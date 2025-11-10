<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\ProblemException;
use App\Support\Http\ProblemType;

final class EntryNotFoundProblem extends ProblemException
{
    public function __construct(int $entryId, bool $trashed = false)
    {
        $detail = $trashed
            ? sprintf('Trashed entry with ID %d does not exist.', $entryId)
            : sprintf('Entry with ID %d does not exist.', $entryId);

        parent::__construct(
            ProblemType::NOT_FOUND,
            detail: $detail,
            title: 'Entry not found',
        );
    }
}
