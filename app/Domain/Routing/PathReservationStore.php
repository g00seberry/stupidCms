<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use Illuminate\Database\QueryException;
use Throwable;

/**
 * Интерфейс хранилища для зарезервированных путей.
 *
 * Определяет контракт для работы с БД для хранения резерваций путей.
 *
 * @package App\Domain\Routing
 */
interface PathReservationStore
{
    /**
     * Вставить новый зарезервированный путь.
     *
     * @param string $path Нормализованный путь
     * @param string $source Источник резервации
     * @param string|null $reason Причина резервации (опционально)
     * @return void
     * @throws \Illuminate\Database\QueryException При ошибке БД
     */
    public function insert(string $path, string $source, ?string $reason): void;

    /**
     * Удалить зарезервированный путь.
     *
     * @param string $path Нормализованный путь
     * @return void
     */
    public function delete(string $path): void;

    /**
     * Удаляет путь только если он принадлежит указанному источнику.
     *
     * Атомарная операция для безопасного удаления с проверкой владельца.
     *
     * @param string $path Нормализованный путь
     * @param string $source Источник резервации
     * @return int Количество удалённых записей (0 или 1)
     */
    public function deleteIfOwnedBy(string $path, string $source): int;

    /**
     * Удалить все пути указанного источника.
     *
     * @param string $source Источник резервации
     * @return int Количество удалённых записей
     */
    public function deleteBySource(string $source): int;

    /**
     * Проверить существование зарезервированного пути.
     *
     * @param string $path Нормализованный путь
     * @return bool true, если путь зарезервирован
     */
    public function exists(string $path): bool;

    /**
     * Получить владельца зарезервированного пути.
     *
     * @param string $path Нормализованный путь
     * @return string|null Источник резервации или null, если путь не зарезервирован
     */
    public function ownerOf(string $path): ?string;

    /**
     * Получает все зарезервированные пути из БД.
     *
     * @return array<string> Массив нормализованных путей
     */
    public function getAllPaths(): array;

    /**
     * Проверить, является ли исключение нарушением уникальности.
     *
     * @param \Throwable $e Исключение для проверки
     * @return bool true, если это нарушение уникальности
     */
    public function isUniqueViolation(Throwable $e): bool;
}

