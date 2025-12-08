<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Реализация сервиса для резервации путей.
 *
 * Управляет зарезервированными путями с поддержкой статических путей из конфига
 * и динамических резерваций из БД. Использует кэширование для оптимизации проверок.
 *
 * @package App\Domain\Routing
 */
final class PathReservationServiceImpl implements PathReservationService
{
    /**
     * @param \App\Domain\Routing\PathReservationStore $store Хранилище резерваций
     * @param array<string> $static Статические пути из конфига, которые нельзя резервировать
     */
    public function __construct(
        private PathReservationStore $store,
        private array $static = []
    ) {}

    /**
     * Пробует зарезервировать путь.
     *
     * @param string $path Путь для резервации
     * @param string $source Источник резервации
     * @param string|null $reason Причина резервации
     * @return void
     * @throws \App\Domain\Routing\Exceptions\PathAlreadyReservedException Если путь уже зарезервирован
     */
    public function reservePath(string $path, string $source, ?string $reason = null): void
    {
        $normalized = PathNormalizer::normalize($path);

        // Блок для статического списка — никогда нельзя резервировать
        if (in_array($normalized, $this->static, true)) {
            throw new PathAlreadyReservedException($normalized, 'static:config');
        }

        // Попытка вставки с уникальным индексом
        try {
            $this->store->insert($normalized, $source, $reason);
            
            // Инвалидируем кэш зарезервированных сегментов после успешной вставки
            Cache::forget('reserved:first-segments');
        } catch (QueryException $e) {
            if ($this->store->isUniqueViolation($e)) {
                $owner = $this->store->ownerOf($normalized) ?? 'unknown';
                throw new PathAlreadyReservedException($normalized, $owner);
            }
            throw $e;
        }
    }

    /**
     * Снять резерв у конкретного пути, если он принадлежит источнику.
     *
     * @param string $path Путь для освобождения
     * @param string $source Источник резервации
     * @return void
     * @throws \App\Domain\Routing\Exceptions\ForbiddenReservationRelease Если путь не принадлежит источнику
     */
    public function releasePath(string $path, string $source): void
    {
        $normalized = PathNormalizer::normalize($path);
        
        // Атомарная операция: удаляем только если владелец совпадает
        $deleted = $this->store->deleteIfOwnedBy($normalized, $source);
        
        if ($deleted === 0) {
            // Путь не найден или принадлежит другому источнику
            $owner = $this->store->ownerOf($normalized);
            if ($owner && $owner !== $source) {
                throw new ForbiddenReservationRelease($normalized, $owner, $source);
            }
            // Если owner === null, путь не существует - это идемпотентная операция, просто выходим
        } else {
            // Инвалидируем кэш зарезервированных сегментов после успешного удаления
            Cache::forget('reserved:first-segments');
        }
    }

    /**
     * Освободить все пути данного источника.
     *
     * @param string $source Источник резервации
     * @return int Количество освобождённых путей
     */
    public function releaseBySource(string $source): int
    {
        $deleted = $this->store->deleteBySource($source);
        
        if ($deleted > 0) {
            // Инвалидируем кэш зарезервированных сегментов после успешного удаления
            Cache::forget('reserved:first-segments');
        }
        
        return $deleted;
    }

    /**
     * Проверить, забронирован ли путь (с учётом статических из config).
     *
     * Использует оптимизацию: сначала проверяет первый сегмент пути через кэш,
     * затем проверяет полный путь в БД только если первый сегмент заблокирован.
     *
     * @param string $path Путь для проверки
     * @return bool true, если путь зарезервирован
     */
    public function isReserved(string $path): bool
    {
        $normalized = PathNormalizer::normalize($path);
        
        // Проверяем статические пути (без кэша, так как они из конфига)
        if (in_array($normalized, $this->static, true)) {
            return true;
        }
        
        // Кэшируем список первых сегментов зарезервированных путей для оптимизации
        // Если первый сегмент не заблокирован, путь точно не зарезервирован
        $firstSegment = strtolower(ltrim(Str::before(ltrim($normalized, '/'), '/'), '/'));
        
        $blocked = Cache::remember('reserved:first-segments', 60, function () {
            return $this->loadFirstSegments();
        });
        
        // Если первый сегмент не заблокирован, путь точно не зарезервирован
        if (!isset($blocked[$firstSegment])) {
            return false;
        }
        
        // Если первый сегмент заблокирован, проверяем полный путь
        // (может быть зарезервирован /admin, но не /admin1)
        return $this->store->exists($normalized);
    }

    /**
     * Загружает список первых сегментов всех зарезервированных путей из БД.
     *
     * Используется для оптимизации проверки isReserved().
     *
     * @return array<string, true> Ассоциативный массив, где ключ - первый сегмент пути
     */
    private function loadFirstSegments(): array
    {
        try {
            $paths = $this->store->getAllPaths();
            $segments = [];
            
            foreach ($paths as $path) {
                $first = strtolower(ltrim(Str::before(ltrim($path, '/'), '/'), '/'));
                if ($first) {
                    $segments[$first] = true;
                }
            }
            
            return $segments;
        } catch (\Exception $e) {
            // Если БД недоступна, возвращаем пустой массив
            return [];
        }
    }

    /**
     * Вернуть владельца пути (источник резервации).
     *
     * @param string $path Путь
     * @return string|null Источник резервации или null, если путь не зарезервирован
     */
    public function ownerOf(string $path): ?string
    {
        $normalized = PathNormalizer::normalize($path);

        // Проверяем статические пути
        if (in_array($normalized, $this->static, true)) {
            return 'static:config';
        }

        return $this->store->ownerOf($normalized);
    }
}

