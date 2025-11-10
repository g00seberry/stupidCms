<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Http\AdminResponseHeaders;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PluginCollection extends ResourceCollection
{
    /**
     * @var class-string<PluginResource>
     */
    public $collects = PluginResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
        ];
    }

    public function withResponse($request, $response): void
    {
        AdminResponseHeaders::apply($response);
    }
}


