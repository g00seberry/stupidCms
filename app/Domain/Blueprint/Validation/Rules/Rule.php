<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Интерфейс доменного правила валидации.
 *
 * Определяет контракт для всех правил валидации, независимых от Laravel.
 * Правила могут быть преобразованы в формат Laravel через адаптер.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
interface Rule
{
    /**
     * Получить тип правила.
     *
     * @return string Тип правила (min, max, pattern, required, nullable и т.д.)
     */
    public function getType(): string;

    /**
     * Получить параметры правила.
     *
     * @return array<string, mixed> Параметры правила (value, pattern, field и т.д.)
     */
    public function getParams(): array;
}

