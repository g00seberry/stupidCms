<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Статус генерации варианта медиа.
 */
enum MediaVariantStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}


