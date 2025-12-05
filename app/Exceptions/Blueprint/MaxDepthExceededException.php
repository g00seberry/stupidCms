<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use LogicException;

/**
 * Исключение: превышена максимальная глубина вложенности встраиваний.
 */
class MaxDepthExceededException extends LogicException
{
    /**
     * Создать исключение для превышения максимальной глубины.
     *
     * @param int $maxDepth Максимально допустимая глубина
     * @return self
     */
    public static function create(int $maxDepth): self
    {
        return new self(
            "Превышена максимальная глубина вложенности встраиваний ({$maxDepth}). " .
            "Упростите структуру blueprint'ов."
        );
    }
}

