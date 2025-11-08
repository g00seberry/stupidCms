<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PostTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->name ?? $this->slug,
            'options_json' => $this->transformOptionsJson($this->options_json),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Recursively normalize options_json to ensure JSON objects remain objects ({}).
     */
    private function transformOptionsJson(mixed $value): mixed
    {
        if ($value === null) {
            return new \stdClass();
        }

        if (! is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return new \stdClass();
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->transformOptionsJson($item), $value);
        }

        $object = new \stdClass();
        foreach ($value as $key => $nested) {
            $object->{$key} = $this->transformOptionsJson($nested);
        }

        return $object;
    }
}

