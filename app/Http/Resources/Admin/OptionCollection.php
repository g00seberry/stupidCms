<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractPaginator;

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
        if (! $this->resource instanceof AbstractPaginator) {
            return $default;
        }

        return $this->buildPagination($default);
    }
}

