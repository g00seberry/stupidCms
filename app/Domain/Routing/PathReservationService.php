<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;

/**
 * Интерфейс сервиса для резервации путей.
 *
 * Определяет контракт для управления зарезервированными путями,
 * которые не могут использоваться для записей контента.
 *
 * @package App\Domain\Routing
 */
interface PathReservationService
{
    /**
     * Пробует зарезервировать путь.
     *
     * Если путь уже занят другим источником, выбрасывает исключение.
     *
     * @param string $path Путь для резервации (должен быть нормализован)
     * @param string $source Источник резервации (например, 'system', 'plugin.name')
     * @param string|null $reason Причина резервации (опционально)
     * @return void
     * @throws \App\Domain\Routing\Exceptions\PathAlreadyReservedException Если путь уже зарезервирован
     */
    public function reservePath(string $path, string $source, ?string $reason = null): void;

    /**
     * Снять резерв у конкретного пути, если он принадлежит источнику.
     *
     * @param string $path Путь для освобождения
     * @param string $source Источник резервации
     * @return void
     * @throws \App\Domain\Routing\Exceptions\ForbiddenReservationRelease Если путь не принадлежит источнику
     */
    public function releasePath(string $path, string $source): void;

    /**
     * Освободить все пути данного источника.
     *
     * @param string $source Источник резервации
     * @return int Количество освобождённых путей
     */
    public function releaseBySource(string $source): int;

    /**
     * Проверить, забронирован ли путь (с учётом статических из config).
     *
     * @param string $path Путь для проверки
     * @return bool true, если путь зарезервирован
     */
    public function isReserved(string $path): bool;

    /**
     * Вернуть владельца пути (источник резервации).
     *
     * @param string $path Путь
     * @return string|null Источник резервации или null, если путь не зарезервирован
     */
    public function ownerOf(string $path): ?string;
}

