<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Доменное правило валидации: проверка соответствия MIME-типа медиа-файла
 * списку допустимых MIME-типов из constraints media-поля.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class MediaMimeRule implements Rule
{
    /**
     * @param array<string> $allowedMimeTypes Список допустимых MIME-типов (например, ['image/jpeg', 'image/png'])
     * @param string $pathFullPath Полный путь к media-полю (для сообщений об ошибках)
     */
    public function __construct(
        private readonly array $allowedMimeTypes,
        private readonly string $pathFullPath
    ) {}

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return 'media_mime';
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return [
            'allowed_mime_types' => $this->allowedMimeTypes,
            'path_full_path' => $this->pathFullPath,
        ];
    }

    /**
     * Получить список допустимых MIME-типов.
     *
     * @return array<string>
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    /**
     * Получить полный путь к media-полю.
     *
     * @return string
     */
    public function getPathFullPath(): string
    {
        return $this->pathFullPath;
    }
}

