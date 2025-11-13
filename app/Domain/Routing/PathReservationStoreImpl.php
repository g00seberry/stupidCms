<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use App\Models\ReservedRoute;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Реализация PathReservationStore с использованием Eloquent.
 *
 * Использует модель ReservedRoute для хранения резерваций в БД.
 *
 * @package App\Domain\Routing
 */
final class PathReservationStoreImpl implements PathReservationStore
{
    /**
     * Вставить новый зарезервированный путь.
     *
     * @param string $path Нормализованный путь
     * @param string $source Источник резервации
     * @param string|null $reason Причина резервации (не используется, оставлено для совместимости)
     * @return void
     */
    public function insert(string $path, string $source, ?string $reason): void
    {
        ReservedRoute::create([
            'path' => $path,
            'kind' => 'path', // По умолчанию все резервации - это пути
            'source' => $source,
        ]);
    }

    /**
     * Удалить зарезервированный путь.
     *
     * @param string $path Нормализованный путь
     * @return void
     */
    public function delete(string $path): void
    {
        ReservedRoute::where('path', $path)->delete();
    }

    /**
     * Удаляет путь только если он принадлежит указанному источнику.
     *
     * @param string $path Нормализованный путь
     * @param string $source Источник резервации
     * @return int Количество удалённых записей (0 или 1)
     */
    public function deleteIfOwnedBy(string $path, string $source): int
    {
        return ReservedRoute::where('path', $path)
            ->where('source', $source)
            ->delete();
    }

    /**
     * Удалить все пути указанного источника.
     *
     * @param string $source Источник резервации
     * @return int Количество удалённых записей
     */
    public function deleteBySource(string $source): int
    {
        return ReservedRoute::where('source', $source)->delete();
    }

    /**
     * Проверить существование зарезервированного пути.
     *
     * @param string $path Нормализованный путь
     * @return bool true, если путь зарезервирован
     */
    public function exists(string $path): bool
    {
        return ReservedRoute::where('path', $path)->exists();
    }

    /**
     * Получить владельца зарезервированного пути.
     *
     * @param string $path Нормализованный путь
     * @return string|null Источник резервации или null, если путь не зарезервирован
     */
    public function ownerOf(string $path): ?string
    {
        $reservation = ReservedRoute::where('path', $path)->first();
        return $reservation?->source;
    }

    /**
     * Получает все зарезервированные пути из БД.
     *
     * @return array<string> Массив нормализованных путей
     */
    public function getAllPaths(): array
    {
        return ReservedRoute::pluck('path')->toArray();
    }

    /**
     * Проверить, является ли исключение нарушением уникальности.
     *
     * Поддерживает SQLite, MySQL/MariaDB и PostgreSQL.
     *
     * @param \Throwable $e Исключение для проверки
     * @return bool true, если это нарушение уникальности
     */
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

