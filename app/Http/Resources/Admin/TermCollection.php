<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Pagination\AbstractPaginator;

class TermCollection extends AdminResourceCollection
{
    public $collects = TermResource::class;

    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function paginationInformation($request, $paginated, $default): array
    {
        if (! $this->resource instanceof AbstractPaginator) {
            return $default;
        }

        return $this->buildPagination($default);
    }
}


