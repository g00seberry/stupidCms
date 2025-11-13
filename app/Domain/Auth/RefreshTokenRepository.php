<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * Интерфейс репозитория для управления refresh токенами.
 *
 * Определяет контракт для работы с refresh токенами: сохранение,
 * пометка как использованных, отзыв, поиск и очистка.
 *
 * @package App\Domain\Auth
 */
interface RefreshTokenRepository
{
    /**
     * Сохранить новый refresh токен в БД.
     *
     * @param array<string, mixed> $data Данные токена: user_id, jti, expires_at, parent_jti?
     * @return void
     */
    public function store(array $data): void;

    /**
     * Условно пометить refresh токен как использованный (только если ещё валиден).
     *
     * Возвращает количество затронутых строк (1 для успеха, 0 если уже использован/отозван/истёк).
     * Это единственный безопасный способ пометить токен как использованный,
     * так как выполняется атомарное условное обновление, предотвращающее гонки и double-spend атаки.
     *
     * @param string $jti JWT ID
     * @return int Количество затронутых строк (0 или 1)
     */
    public function markUsedConditionally(string $jti): int;

    /**
     * Отозвать refresh токен (logout/admin действие).
     *
     * @param string $jti JWT ID
     * @return void
     */
    public function revoke(string $jti): void;

    /**
     * Отозвать токен и всех его потомков в цепочке обновления (инвалидация семейства токенов).
     *
     * Используется при обнаружении атаки повторного использования токена.
     *
     * @param string $jti JWT ID токена для отзыва
     * @return int Количество отозванных токенов (включая сам токен и всех потомков)
     */
    public function revokeFamily(string $jti): int;

    /**
     * Найти refresh токен по его JTI.
     *
     * @param string $jti JWT ID
     * @return \App\Domain\Auth\RefreshTokenDto|null DTO токена или null, если не найден
     */
    public function find(string $jti): ?RefreshTokenDto;

    /**
     * Удалить истёкшие refresh токены (очистка).
     *
     * @return int Количество удалённых токенов
     */
    public function deleteExpired(): int;
}

