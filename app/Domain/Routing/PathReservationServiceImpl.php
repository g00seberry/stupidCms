<?php

namespace App\Domain\Routing;

use App\Domain\Routing\Exceptions\ForbiddenReservationRelease;
use App\Domain\Routing\Exceptions\PathAlreadyReservedException;
use Illuminate\Database\QueryException;

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
        }
    }

    public function releaseBySource(string $source): int
    {
        return $this->store->deleteBySource($source);
    }

    public function isReserved(string $path): bool
    {
        $normalized = PathNormalizer::normalize($path);
        return in_array($normalized, $this->static, true) || $this->store->exists($normalized);
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

