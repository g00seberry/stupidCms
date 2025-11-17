<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Contracts;

/**
 * Контракт для синхронизации плагинов.
 *
 * @package App\Domain\Plugins\Contracts
 */
interface PluginsSynchronizerInterface
{
    /**
     * Синхронизировать плагины из файловой системы в БД.
     *
     * @return array{added: int, updated: int, removed: int, providers: list<string>} Статистика синхронизации
     * @throws \App\Domain\Plugins\Exceptions\InvalidPluginManifest Если манифест плагина невалиден
     */
    public function sync(): array;
}

