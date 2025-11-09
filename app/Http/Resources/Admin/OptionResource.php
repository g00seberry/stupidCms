<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Option;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

class OptionResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'namespace' => $this->namespace,
            'key' => $this->key,
            'value' => $this->value_json,
            'description' => $this->description,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    public function withResponse($request, $response): void
    {
        if ($this->resource instanceof Option && $this->resource->wasRecentlyCreated) {
            $response->setStatusCode(Response::HTTP_CREATED);
        }

        $response->header('Cache-Control', 'no-store, private');
        $response->header('Vary', 'Cookie');
    }
}

