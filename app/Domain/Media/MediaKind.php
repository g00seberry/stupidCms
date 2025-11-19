<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Enum для типов медиа-файлов.
 *
 * Определяет типы медиа: изображения, видео, аудио, документы.
 * Используется для типобезопасной работы с типами медиа вместо строковых значений.
 *
 * @package App\Domain\Media
 */
enum MediaKind: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';

    /**
     * Определить тип медиа по MIME-типу.
     *
     * Анализирует MIME-тип и возвращает соответствующий MediaKind:
     * - image/* → Image
     * - video/* → Video
     * - audio/* → Audio
     * - остальное → Document
     *
     * @param string $mime MIME-тип файла
     * @return self Тип медиа-файла
     */
    public static function fromMime(string $mime): self
    {
        return match (true) {
            str_starts_with($mime, 'image/') => self::Image,
            str_starts_with($mime, 'video/') => self::Video,
            str_starts_with($mime, 'audio/') => self::Audio,
            default => self::Document,
        };
    }
}

