<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Events;

use App\Models\Plugin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: плагин включён.
 *
 * Отправляется после успешного включения плагина в БД.
 *
 * @package App\Domain\Plugins\Events
 */
final class PluginEnabled
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param \App\Models\Plugin $plugin Включённый плагин
     */
    public function __construct(
        public readonly Plugin $plugin,
    ) {
    }
}

