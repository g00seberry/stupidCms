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

        $paginator = $this->resource;

        $links = [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];

        $meta = [
            'current_page' => (int) $paginator->currentPage(),
            'from' => $paginator->firstItem() !== null ? (int) $paginator->firstItem() : null,
            'last_page' => (int) $paginator->lastPage(),
            'path' => $paginator->path(),
            'per_page' => (int) $paginator->perPage(),
            'to' => $paginator->lastItem() !== null ? (int) $paginator->lastItem() : null,
            'total' => (int) $paginator->total(),
        ];

        return [
            'links' => array_merge($default['links'] ?? [], $links),
            'meta' => array_merge($default['meta'] ?? [], $meta),
        ];
    }
}


