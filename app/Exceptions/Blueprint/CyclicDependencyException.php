<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use LogicException;

/**
 * Исключение: попытка создать циклическую зависимость между blueprint'ами.
 *
 * Выбрасывается при попытке встроить blueprint A в B,
 * если B уже зависит от A (прямо или транзитивно).
 */
class CyclicDependencyException extends LogicException
{
    /**
     * Создать исключение для попытки встроить blueprint в самого себя.
     *
     * @param string $blueprintCode Код blueprint
     * @return self
     */
    public static function selfEmbed(string $blueprintCode): self
    {
        return new self("Нельзя встроить blueprint '{$blueprintCode}' в самого себя.");
    }

    /**
     * Создать исключение для циклической зависимости.
     *
     * @param string $hostCode Код host blueprint (кто встраивает)
     * @param string $embeddedCode Код embedded blueprint (кого встраивают)
     * @return self
     */
    public static function circularDependency(string $hostCode, string $embeddedCode): self
    {
        return new self(
            "Циклическая зависимость: '{$embeddedCode}' уже зависит от '{$hostCode}' " .
            "(прямо или транзитивно). Встраивание невозможно."
        );
    }
}

