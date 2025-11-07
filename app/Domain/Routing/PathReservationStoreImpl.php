<?php

namespace App\Domain\Routing;

use App\Models\ReservedRoute;
use Illuminate\Database\QueryException;
use Throwable;

final class PathReservationStoreImpl implements PathReservationStore
{
    public function insert(string $path, string $source, ?string $reason): void
    {
        ReservedRoute::create([
            'path' => $path,
            'kind' => 'path', // По умолчанию все резервации - это пути
            'source' => $source,
        ]);
    }

    public function delete(string $path): void
    {
        ReservedRoute::where('path', $path)->delete();
    }

    public function deleteIfOwnedBy(string $path, string $source): int
    {
        return ReservedRoute::where('path', $path)
            ->where('source', $source)
            ->delete();
    }

    public function deleteBySource(string $source): int
    {
        return ReservedRoute::where('source', $source)->delete();
    }

    public function exists(string $path): bool
    {
        return ReservedRoute::where('path', $path)->exists();
    }

    public function ownerOf(string $path): ?string
    {
        $reservation = ReservedRoute::where('path', $path)->first();
        return $reservation?->source;
    }

    public function getAllPaths(): array
    {
        return ReservedRoute::pluck('path')->toArray();
    }

    public function isUniqueViolation(Throwable $e): bool
    {
        if (!$e instanceof QueryException) {
            return false;
        }

        // SQLite
        if (str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
            return true;
        }

        // MySQL/MariaDB
        if (str_contains($e->getMessage(), 'Duplicate entry') || (string)$e->getCode() === '23000') {
            return true;
        }

        // PostgreSQL
        if (str_contains($e->getMessage(), 'duplicate key value') || (string)$e->getCode() === '23505') {
            return true;
        }

        return false;
    }
}

