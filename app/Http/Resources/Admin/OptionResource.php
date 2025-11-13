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
            'value' => $this->transformJson($this->value_json),
            'description' => $this->description,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    private function transformJson(mixed $value): mixed
    {
        if ($value === null || $value === []) {
            return new \stdClass();
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->transformJson($item), $value);
        }

        $object = new \stdClass();
        foreach ($value as $key => $nested) {
            $object->{$key} = $this->transformJson($nested);
        }

        return $object;
    }

    protected function prepareAdminResponse($request, Response $response): void
    {
        if ($this->resource instanceof Option && $this->resource->wasRecentlyCreated) {
            $response->setStatusCode(Response::HTTP_CREATED);
        }

        parent::prepareAdminResponse($request, $response);
    }
}

