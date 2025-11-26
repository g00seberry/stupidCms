<?php

declare(strict_types=1);

namespace App\Listeners\Blueprint;

use App\Domain\Blueprint\Validation\BlueprintContentValidator;
use App\Events\Blueprint\BlueprintStructureChanged;
use Illuminate\Support\Facades\Log;

/**
 * Listener: инвалидация кэша правил валидации при изменении структуры blueprint.
 *
 * Обрабатывает событие BlueprintStructureChanged и инвалидирует кэш правил валидации
 * для изменённого blueprint.
 *
 * @package App\Listeners\Blueprint
 */
class InvalidateValidationCache
{
    /**
     * @param \App\Domain\Blueprint\Validation\BlueprintContentValidator $validator Валидатор для инвалидации кэша
     */
    public function __construct(
        private readonly BlueprintContentValidator $validator
    ) {
    }

    /**
     * Обработать событие изменения структуры blueprint.
     *
     * Инвалидирует кэш правил валидации для изменённого blueprint.
     *
     * @param \App\Events\Blueprint\BlueprintStructureChanged $event Событие изменения структуры
     * @return void
     */
    public function handle(BlueprintStructureChanged $event): void
    {
        $blueprint = $event->blueprint;

        try {
            // Инвалидируем кэш для изменённого blueprint
            $this->validator->invalidateCache($blueprint);
            Log::debug("Инвалидирован кэш правил валидации для blueprint '{$blueprint->code}' (ID: {$blueprint->id})");
        } catch (\Exception $e) {
            Log::error("Ошибка инвалидации кэша валидации для blueprint ID {$blueprint->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);
        }
    }
}

