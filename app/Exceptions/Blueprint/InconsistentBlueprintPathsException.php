<?php

declare(strict_types=1);

namespace App\Exceptions\Blueprint;

use RuntimeException;

/**
 * Исключение: несоответствие путей blueprint'а.
 *
 * Выбрасывается, когда структура путей blueprint'а некорректна
 * (например, родительский путь не найден при обработке).
 */
final class InconsistentBlueprintPathsException extends RuntimeException
{
    /**
     * Создать исключение для случая, когда родительский путь не найден.
     *
     * @param int $sourcePathId ID исходного пути
     * @param string $sourcePathName Имя исходного пути
     * @param int $parentId ID родительского пути
     * @return self
     */
    public static function forParentNotFound(int $sourcePathId, string $sourcePathName, int $parentId): self
    {
        return new self(
            "Parent path for source path ID {$sourcePathId} (name: {$sourcePathName}, parent_id: {$parentId}) not found. " .
            "This indicates paths are not sorted correctly (parents before children)."
        );
    }
}

