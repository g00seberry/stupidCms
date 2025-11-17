<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Contracts;

/**
 * Контракт для перезагрузки маршрутов плагинов.
 *
 * @package App\Domain\Plugins\Contracts
 */
interface RouteReloader
{
    /**
     * Перезагрузить маршруты плагинов.
     *
     * @return void
     * @throws \App\Domain\Plugins\Exceptions\RoutesReloadFailed Если перезагрузка не удалась
     */
    public function reload(): void;
}

