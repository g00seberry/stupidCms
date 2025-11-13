<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: изменён slug записи.
 *
 * Диспатчится при изменении slug записи Entry.
 * Используется для создания редиректов со старого slug на новый.
 *
 * @package App\Events
 */
class EntrySlugChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param int $entryId ID записи
     * @param string $old Старый slug
     * @param string $new Новый slug
     */
    public function __construct(
        public readonly int $entryId,
        public readonly string $old,
        public readonly string $new
    ) {}
}

