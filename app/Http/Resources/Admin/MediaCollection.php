<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Http\Resources\MediaResource;

class MediaCollection extends AdminResourceCollection
{
    /**
     * @var class-string<MediaResource>
     */
    public $collects = MediaResource::class;

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


