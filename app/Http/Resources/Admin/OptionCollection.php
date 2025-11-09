<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class OptionCollection extends AdminResourceCollection
{
    /**
     * @var class-string<JsonResource>
     */
    public $collects = OptionResource::class;

    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    /**
     * @param mixed $request
     * @param array<string, mixed> $paginated
     * @param array<string, mixed> $default
     * @return array<string, mixed>
     */
    public function paginationInformation($request, $paginated, $default): array
    {
        if (! $this->resource instanceof LengthAwarePaginator) {
            return $default;
        }

        $links = [
            'first' => $this->resource->url(1),
            'last' => $this->resource->url($this->resource->lastPage()),
            'prev' => $this->resource->previousPageUrl(),
            'next' => $this->resource->nextPageUrl(),
        ];

        $meta = [
            'page' => (int) $this->resource->currentPage(),
            'per_page' => (int) $this->resource->perPage(),
            'total' => (int) $this->resource->total(),
        ];

        return [
            'links' => array_merge($default['links'] ?? [], $links),
            'meta' => array_merge($default['meta'] ?? [], $meta),
        ];
    }
}

