<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class TaxonomyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label ?? $this->name,
            'hierarchical' => (bool) $this->hierarchical,
            'options_json' => $this->transformJson($this->options_json),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
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


