<?php

namespace App\Domain\Routing;

use App\Models\RouteReservation;
use Illuminate\Database\QueryException;
use Throwable;

final class PathReservationStoreImpl implements PathReservationStore
{
    public function insert(string $path, string $source, ?string $reason): void
    {
        RouteReservation::create([
            'path' => $path,
            'source' => $source,
            'reason' => $reason,
        ]);
    }

    public function delete(string $path): void
    {
        RouteReservation::where('path', $path)->delete();
    }

    public function deleteIfOwnedBy(string $path, string $source): int
    {
        return RouteReservation::where('path', $path)
            ->where('source', $source)
            ->delete();
    }

    public function deleteBySource(string $source): int
    {
        return RouteReservation::where('source', $source)->delete();
    }

    public function exists(string $path): bool
    {
        return RouteReservation::where('path', $path)->exists();
    }

    public function ownerOf(string $path): ?string
    {
        $reservation = RouteReservation::where('path', $path)->first();
        return $reservation?->source;
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

