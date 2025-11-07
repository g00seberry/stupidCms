<?php

namespace App\Domain\Routing;

interface PathReservationService
{
    /**
     * Пробует зарезервировать путь. Если уже занят — бросает PathAlreadyReservedException.
     */
    public function reservePath(string $path, string $source, ?string $reason = null): void;

    /**
     * Снять резерв у конкретного пути, если он принадлежит источнику; иначе — бросает ForbiddenReservationRelease.
     */
    public function releasePath(string $path, string $source): void;

    /**
     * Освободить все пути данного источника.
     */
    public function releaseBySource(string $source): int;

    /**
     * Проверить, забронирован ли путь (с учётом статических из config).
     */
    public function isReserved(string $path): bool;

    /**
     * Вернуть владельца (или null).
     */
    public function ownerOf(string $path): ?string;
}

