<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource для Blueprint.
 *
 * @mixin \App\Models\Blueprint
 */
class BlueprintResource extends JsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_type_id' => $this->post_type_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Связи
            'post_type' => $this->whenLoaded('postType', function () {
                return [
                    'id' => $this->postType->id,
                    'slug' => $this->postType->slug,
                    'name' => $this->postType->name,
                ];
            }),

            'paths' => PathResource::collection($this->whenLoaded('paths')),
            'components' => BlueprintResource::collection($this->whenLoaded('components')),

            // Статистика
            'entries_count' => $this->when(
                isset($this->entries_count),
                $this->entries_count
            ),
        ];
    }
}

