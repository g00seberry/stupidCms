<?php

declare(strict_types=1);

namespace App\Events\Blueprint;

use App\Models\Blueprint;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие: структура blueprint изменена.
 *
 * Триггерится при:
 * - Добавлении/удалении/изменении Path
 * - Добавлении/удалении BlueprintEmbed
 * - Изменении свойств Path (name, data_type, cardinality и т.д.)
 *
 * Запускает каскадную рематериализацию всех зависимых blueprint'ов.
 */
class BlueprintStructureChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param Blueprint $blueprint Изменённый blueprint
     * @param array<int> $processedBlueprints ID blueprint'ов, уже обработанных в цепочке
     */
    public function __construct(
        public readonly Blueprint $blueprint,
        public readonly array $processedBlueprints = []
    ) {}

    /**
     * Проверить, был ли blueprint уже обработан (защита от циклов).
     *
     * @param int $blueprintId
     * @return bool
     */
    public function wasProcessed(int $blueprintId): bool
    {
        return in_array($blueprintId, $this->processedBlueprints, true);
    }

    /**
     * Создать новое событие с добавленным blueprint в список обработанных.
     *
     * @param int $blueprintId
     * @return self
     */
    public function withProcessed(int $blueprintId): self
    {
        return new self(
            $this->blueprint,
            array_merge($this->processedBlueprints, [$blueprintId])
        );
    }
}

