<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

class TaxonomyCollection extends AdminResourceCollection
{
    /**
     * @var class-string<TaxonomyResource>
     */
    public $collects = TaxonomyResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}


