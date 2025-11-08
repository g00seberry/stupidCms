<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

class EntryCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = EntryResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    /**
     * Customize the response after transformation.
     */
    public function withResponse($request, $response): void
    {
        $response->header('Cache-Control', 'no-store, private');
        $response->header('Vary', 'Cookie');
    }

    /**
     * Customize pagination information to enforce type consistency.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation($request, $paginated, $default): array
    {
        if (! $this->resource instanceof AbstractPaginator) {
            return $default;
        }

        $paginator = $this->resource;
        $firstItem = $paginator->firstItem();
        $lastItem = $paginator->lastItem();

        $links = [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];

        $meta = [
            'current_page' => (int) $paginator->currentPage(),
            'from' => $firstItem !== null ? (int) $firstItem : null,
            'last_page' => (int) $paginator->lastPage(),
            'path' => $paginator->path(),
            'per_page' => (int) $paginator->perPage(),
            'to' => $lastItem !== null ? (int) $lastItem : null,
            'total' => (int) $paginator->total(),
        ];

        return [
            'links' => array_merge($default['links'] ?? [], $links),
            'meta' => array_merge($default['meta'] ?? [], $meta),
        ];
    }
}

