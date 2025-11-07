<?php

namespace App\Domain\Routing;

use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class PathReservationServiceImpl implements PathReservationService
{
    /**
     * @param array<string> $static Статические пути из конфига, которые нельзя резервировать
     */
    public function __construct(
        private PathReservationStore $store,
        private array $static = []
    ) {}

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

    public function releaseBySource(string $source): int
    {
        $deleted = $this->store->deleteBySource($source);
        
        if ($deleted > 0) {
            // Инвалидируем кэш зарезервированных сегментов после успешного удаления
            Cache::forget('reserved:first-segments');
        }
        
        return $deleted;
    }

    public function isReserved(string $path): bool
    {
        $normalized = PathNormalizer::normalize($path);
        
        // Проверяем статические пути (без кэша, так как они из конфига)
        if (in_array($normalized, $this->static, true)) {
            return true;
        }
        
        // Кэшируем список первых сегментов зарезервированных путей для оптимизации
        // Это снижает нагрузку на БД при частых проверках в PageController
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

