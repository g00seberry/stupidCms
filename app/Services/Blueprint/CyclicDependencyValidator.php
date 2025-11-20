<?php

declare(strict_types=1);

namespace App\Services\Blueprint;

use App\Exceptions\Blueprint\CyclicDependencyException;
use App\Models\Blueprint;

/**
 * Валидатор циклических зависимостей между blueprint'ами.
 *
 * Проверяет, что создание нового встраивания не приведёт к циклу в графе.
 */
class CyclicDependencyValidator
{
    /**
     * @param DependencyGraphService $graphService Сервис обхода графа
     */
    public function __construct(
        private readonly DependencyGraphService $graphService
    ) {}

    /**
     * Проверить, что встраивание blueprint'а не создаст цикл.
     *
     * Проверяет:
     * 1. host.id != embedded.id (нельзя встроить в самого себя)
     * 2. Не существует пути embedded → host (иначе цикл)
     *
     * @param Blueprint $host Кто встраивает
     * @param Blueprint $embedded Кого встраивают
     * @return void
     * @throws CyclicDependencyException
     */
    public function ensureNoCyclicDependency(Blueprint $host, Blueprint $embedded): void
    {
        // Проверка 1: нельзя встроить в самого себя
        if ($host->id === $embedded->id) {
            throw CyclicDependencyException::selfEmbed($host->code);
        }

        // Проверка 2: нет пути embedded → host
        // Если embedded уже зависит от host (прямо или транзитивно),
        // то добавление host → embedded создаст цикл
        if ($this->graphService->hasPathTo($embedded->id, $host->id)) {
            throw CyclicDependencyException::circularDependency(
                $host->code,
                $embedded->code
            );
        }
    }

    /**
     * Проверить, можно ли встроить blueprint (обёртка для удобства).
     *
     * @param int $hostId ID host blueprint
     * @param int $embeddedId ID embedded blueprint
     * @return bool true, если встраивание не создаст цикл
     */
    public function canEmbed(int $hostId, int $embeddedId): bool
    {
        if ($hostId === $embeddedId) {
            return false;
        }

        return !$this->graphService->hasPathTo($embeddedId, $hostId);
    }
}

