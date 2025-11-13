<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Events;

use App\Models\Plugin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: плагин отключён.
 *
 * Отправляется после успешного отключения плагина в БД.
 *
 * @package App\Domain\Plugins\Events
 */
final class PluginDisabled
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param \App\Models\Plugin $plugin Отключённый плагин
     */
    public function __construct(
        public readonly Plugin $plugin,
    ) {
    }
}

