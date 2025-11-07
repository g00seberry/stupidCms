<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntrySlugChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $entryId,
        public readonly string $old,
        public readonly string $new
    ) {}
}

