<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\Admin\Concerns\ConfiguresAdminResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Symfony\Component\HttpFoundation\Response;

abstract class AdminResourceCollection extends ResourceCollection
{
    use ConfiguresAdminResponse;

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  Response  $response
     */
    public function withResponse($request, $response): void
    {
        $this->prepareAdminResponse($request, $response);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  Response  $response
     */
    protected function prepareAdminResponse($request, Response $response): void
    {
        $this->addAdminResponseHeaders($response);
    }

    /**
     * Формирует стандартную структуру пагинации.
     *
     * @param  AbstractPaginator  $paginator
     * @param  array<string, mixed>  $default
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function buildPagination(array $default, ?array $meta = null): array
    {
        if (! $this->resource instanceof AbstractPaginator) {
            return $default;
        }

        $paginator = $this->resource;
        $meta ??= $this->paginationMeta($paginator);

        return [
            'links' => array_merge($default['links'] ?? [], $this->paginatorLinks($paginator)),
            'meta' => array_merge($default['meta'] ?? [], $meta),
        ];
    }

    /**
     * @return array<string, int|null|string>
     */
    protected function paginationMeta(AbstractPaginator $paginator): array
    {
        $firstItem = $paginator->firstItem();
        $lastItem = $paginator->lastItem();

        return [
            'current_page' => (int) $paginator->currentPage(),
            'from' => $firstItem !== null ? (int) $firstItem : null,
            'last_page' => (int) $paginator->lastPage(),
            'path' => $paginator->path(),
            'per_page' => (int) $paginator->perPage(),
            'to' => $lastItem !== null ? (int) $lastItem : null,
            'total' => (int) $paginator->total(),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function paginatorLinks(AbstractPaginator $paginator): array
    {
        return [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }
}


