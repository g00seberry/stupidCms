<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PluginsSynced
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $added,
        public readonly int $updated,
        public readonly int $removed,
        /** @var list<string> */
        public readonly array $providers,
    ) {
    }
}

