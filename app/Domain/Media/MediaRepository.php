<?php

declare(strict_types=1);

namespace App\Domain\Media;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Контракт репозитория для выборки медиа.
 */
interface MediaRepository
{
    /**
     * Построить Eloquent Builder по MediaQuery.
     *
     * @param \App\Domain\Media\MediaQuery $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function buildQuery(MediaQuery $query): Builder;

    /**
     * Пагинация по MediaQuery.
     *
     * @param \App\Domain\Media\MediaQuery $query
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate(MediaQuery $query): LengthAwarePaginator;

    /**
     * Получить коллекцию без пагинации (с лимитом).
     *
     * @param \App\Domain\Media\MediaQuery $query
     * @param int|null $limit
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Media>
     */
    public function get(MediaQuery $query, ?int $limit = null): Collection;
}


