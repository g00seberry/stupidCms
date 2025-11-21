<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use RuntimeException;

/**
 * Исключение: не найдена копия host_path для embed.
 *
 * Выбрасывается, когда при разворачивании вложенных embeds
 * не найдена копия host_path в карте соответствия.
 */
final class HostPathCopyNotFoundException extends RuntimeException
{
    /**
     * Создать исключение для случая, когда не найдена копия host_path.
     *
     * @param int $embedId ID embed'а
     * @return self
     */
    public static function forEmbed(int $embedId): self
    {
        return new self(
            "Не найдена копия host_path для embed {$embedId}."
        );
    }
}

