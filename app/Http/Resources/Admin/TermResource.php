<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class TermResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'taxonomy' => $this->taxonomy?->slug,
            'name' => $this->name,
            'slug' => $this->slug,
            'meta_json' => $this->transformJson($this->meta_json),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    public function withResponse($request, $response): void
    {
        $response->header('Cache-Control', 'no-store, private');
        $response->header('Vary', 'Cookie');
    }

    private function transformJson(mixed $value): mixed
    {
        if ($value === null) {
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
}


