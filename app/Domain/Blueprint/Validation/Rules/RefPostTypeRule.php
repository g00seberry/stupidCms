<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Validation\Rules;

/**
 * Доменное правило валидации: проверка соответствия post_type_id целевого Entry
 * списку допустимых post_type_id из constraints ref-поля.
 *
 * @package App\Domain\Blueprint\Validation\Rules
 */
final class RefPostTypeRule implements Rule
{
    /**
     * @param array<int> $allowedPostTypeIds Список допустимых post_type_id
     * @param string $pathFullPath Полный путь к ref-полю (для сообщений об ошибках)
     */
    public function __construct(
        private readonly array $allowedPostTypeIds,
        private readonly string $pathFullPath
    ) {}

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return 'ref_post_type';
    }

    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return [
            'allowed_post_type_ids' => $this->allowedPostTypeIds,
            'path_full_path' => $this->pathFullPath,
        ];
    }

    /**
     * Получить список допустимых post_type_id.
     *
     * @return array<int>
     */
    public function getAllowedPostTypeIds(): array
    {
        return $this->allowedPostTypeIds;
    }

    /**
     * Получить полный путь к ref-полю.
     *
     * @return string
     */
    public function getPathFullPath(): string
    {
        return $this->pathFullPath;
    }
}

