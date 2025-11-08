<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class EntryResource extends JsonResource
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
            'id' => $this->id,
            'post_type' => $this->postType?->slug,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'content_json' => $this->transformJson($this->data_json),
            'meta_json' => $this->transformJson($this->seo_json),
            'is_published' => $this->status === 'published',
            'published_at' => $this->published_at?->toIso8601String(),
            'template_override' => $this->template_override,
            'author' => $this->when($this->relationLoaded('author'), function () {
                return [
                    'id' => $this->author?->id,
                    'name' => $this->author?->name,
                ];
            }),
            'terms' => $this->when($this->relationLoaded('terms'), function () {
                return $this->terms->map(function ($term) {
                    return [
                        'id' => $term->id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'taxonomy' => $term->taxonomy?->slug,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * Recursively transform JSON data to ensure empty arrays become empty objects.
     */
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

    /**
     * Customize the response after transformation.
     */
    public function withResponse($request, $response): void
    {
        $response->header('Cache-Control', 'no-store, private');
        $response->header('Vary', 'Cookie');
    }
}

