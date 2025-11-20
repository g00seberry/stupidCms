<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use LogicException;

/**
 * Исключение: конфликт full_path при встраивании blueprint.
 *
 * Выбрасывается, когда материализация создаст path с full_path,
 * который уже существует в host blueprint.
 */
class PathConflictException extends LogicException
{
    /**
     * Создать исключение для конфликта путей.
     *
     * @param string $hostCode Код host blueprint
     * @param string $embeddedCode Код embedded blueprint
     * @param array<string> $conflictingPaths Список конфликтующих путей
     * @return self
     */
    public static function create(
        string $hostCode,
        string $embeddedCode,
        array $conflictingPaths
    ): self {
        $pathsList = implode(', ', array_map(fn($p) => "'$p'", $conflictingPaths));
        
        return new self(
            "Невозможно встроить blueprint '{$embeddedCode}' в '{$hostCode}': " .
            "конфликт путей: {$pathsList}. " .
            "Переименуйте поля или измените host_path."
        );
    }
}

