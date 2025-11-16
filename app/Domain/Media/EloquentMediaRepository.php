<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Models\Media;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Реализация MediaRepository на базе Eloquent.
 */
final class EloquentMediaRepository implements MediaRepository
{
    /**
     * Построить запрос Eloquent по параметрам MediaQuery.
     *
     * @param \App\Domain\Media\MediaQuery $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function buildQuery(MediaQuery $query): Builder
    {
        $builder = Media::query();

        // deleted filter
        match ($query->deletedFilter()) {
            MediaDeletedFilter::WithDeleted => $builder->withTrashed(),
            MediaDeletedFilter::OnlyDeleted => $builder->onlyTrashed(),
            MediaDeletedFilter::DefaultOnlyNotDeleted => null,
        };

        // search
        if ($query->search() !== null && $query->search() !== '') {
            $term = $query->search();
            $builder->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('original_name', 'like', "%{$term}%");
            });
        }

        // kind
        if ($query->kind()) {
            $kind = $query->kind();
            if ($kind === 'document') {
                $builder->where(function (Builder $q) {
                    $q->where('mime', 'not like', 'image/%')
                        ->where('mime', 'not like', 'video/%')
                        ->where('mime', 'not like', 'audio/%');
                });
            } else {
                $prefix = match ($kind) {
                    'image' => 'image/%',
                    'video' => 'video/%',
                    'audio' => 'audio/%',
                    default => null,
                };
                if ($prefix) {
                    $builder->where('mime', 'like', $prefix);
                }
            }
        }

        // mime prefix
        if ($query->mimePrefix()) {
            $builder->where('mime', 'like', $query->mimePrefix() . '%');
        }

        // collection
        if ($query->collection()) {
            $builder->where('collection', $query->collection());
        }

        // sort/order
        $builder->orderBy($query->sort(), $query->order());

        return $builder;
    }

    /**
     * Выполнить пагинацию результатов по параметрам MediaQuery.
     *
     * @param \App\Domain\Media\MediaQuery $query
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate(MediaQuery $query): LengthAwarePaginator
    {
        $builder = $this->buildQuery($query);
        return $builder->paginate($query->perPage(), ['*'], 'page', $query->page());
    }

    /**
     * Получить коллекцию результатов без пагинации (с опциональным лимитом).
     *
     * @param \App\Domain\Media\MediaQuery $query
     * @param int|null $limit
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Media>
     */
    public function get(MediaQuery $query, ?int $limit = null): Collection
    {
        $builder = $this->buildQuery($query);
        if ($limit !== null) {
            $builder->limit($limit);
        }
        /** @var \Illuminate\Database\Eloquent\Collection<int, Media> $result */
        $result = $builder->get();
        return $result;
    }
}


