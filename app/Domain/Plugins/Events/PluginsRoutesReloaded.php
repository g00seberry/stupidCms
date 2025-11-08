<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PluginsRoutesReloaded
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param list<string> $providers
     */
    public function __construct(
        public readonly array $providers,
    ) {
    }
}

