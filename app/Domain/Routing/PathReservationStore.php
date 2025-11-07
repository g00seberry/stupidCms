<?php

namespace App\Domain\Routing;

use App\Models\RouteReservation;
use Illuminate\Database\QueryException;
use Throwable;

interface PathReservationStore
{
    public function insert(string $path, string $source, ?string $reason): void;
    public function delete(string $path): void;
    /**
     * Удаляет путь только если он принадлежит указанному источнику.
     * Возвращает количество удалённых записей (0 или 1).
     */
    public function deleteIfOwnedBy(string $path, string $source): int;
    public function deleteBySource(string $source): int;
    public function exists(string $path): bool;
    public function ownerOf(string $path): ?string;
    public function isUniqueViolation(Throwable $e): bool;
}

