<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: маршруты плагинов перезагружены.
 *
 * Отправляется после успешной перезагрузки маршрутов плагинов.
 *
 * @package App\Domain\Plugins\Events
 */
final class PluginsRoutesReloaded
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param list<string> $providers Список FQCN провайдеров, маршруты которых перезагружены
     */
    public function __construct(
        public readonly array $providers,
    ) {
    }
}

