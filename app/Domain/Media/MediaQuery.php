<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Domain\Media\MediaDeletedFilter as DeletedFilter;

/**
 * Value Object для параметров выборки медиа.
 */
final class MediaQuery
{
    public function __construct(
        private readonly ?string $search,
        private readonly ?string $kind,
        private readonly ?string $mimePrefix,
        private readonly DeletedFilter $deletedFilter,
        private readonly string $sort,
        private readonly string $order,
        private readonly int $page,
        private readonly int $perPage,
    ) {
    }

    /**
     * Текст поискового запроса (по title и original_name).
     *
     * @return string|null
     */
    public function search(): ?string
    {
        return $this->search;
    }

    /**
     * Тип медиа: image|video|audio|document.
     *
     * @return string|null
     */
    public function kind(): ?string
    {
        return $this->kind;
    }

    /**
     * Префикс MIME для фильтрации (например, image/).
     *
     * @return string|null
     */
    public function mimePrefix(): ?string
    {
        return $this->mimePrefix;
    }

    /**
     * Правила учёта soft-deleted записей.
     *
     * @return \App\Domain\Media\MediaDeletedFilter
     */
    public function deletedFilter(): DeletedFilter
    {
        return $this->deletedFilter;
    }

    /**
     * Поле сортировки.
     *
     * @return string
     */
    public function sort(): string
    {
        return $this->sort;
    }

    /**
     * Направление сортировки: asc|desc.
     *
     * @return string
     */
    public function order(): string
    {
        return $this->order;
    }

    /**
     * Номер страницы.
     *
     * @return int
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Размер страницы (1-100).
     *
     * @return int
     */
    public function perPage(): int
    {
        return $this->perPage;
    }
}


