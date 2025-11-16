<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Управление выборкой с учётом soft deletes.
 */
enum MediaDeletedFilter: string
{
    case DefaultOnlyNotDeleted = 'default';
    case WithDeleted = 'with';
    case OnlyDeleted = 'only';
}


