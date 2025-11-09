<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Option;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OptionResource extends AdminJsonResource
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

    protected function prepareAdminResponse($request, Response $response): void
    {
        if ($this->resource instanceof Option && $this->resource->wasRecentlyCreated) {
            $response->setStatusCode(Response::HTTP_CREATED);
        }

        parent::prepareAdminResponse($request, $response);
    }
}

