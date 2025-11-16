<?php

declare(strict_types=1);

namespace App\Domain\Media\Actions;

use App\Domain\Media\MediaQuery;
use App\Domain\Media\MediaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * CQRS-действие: выборка списка медиа по параметрам запроса.
 */
final class ListMediaAction
{
    public function __construct(
        private readonly MediaRepository $repository,
    ) {
    }

    /**
     * Выполнить пагинацию медиа.
     *
     * @param \App\Domain\Media\MediaQuery $query Параметры фильтров/сортировки/пагинации
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function execute(MediaQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }
}


