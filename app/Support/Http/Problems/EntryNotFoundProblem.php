<?php

declare(strict_types=1);

namespace App\Support\Http\Problems;

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemType;
use App\Support\Problems\Problem;

final class EntryNotFoundProblem extends HttpProblemException
{
    public function __construct(int $entryId, bool $trashed = false)
    {
        $detail = $trashed
            ? sprintf('Trashed entry with ID %d does not exist.', $entryId)
            : sprintf('Entry with ID %d does not exist.', $entryId);

        parent::__construct(
            Problem::of(ProblemType::NOT_FOUND)
                ->detail($detail)
                ->title('Entry not found')
        );
    }
}
