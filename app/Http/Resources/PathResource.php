<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource для Path.
 *
 * @mixin \App\Models\Path
 */
class PathResource extends JsonResource
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
            'blueprint_id' => $this->blueprint_id,
            'source_component_id' => $this->source_component_id,
            'source_path_id' => $this->source_path_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'full_path' => $this->full_path,
            'data_type' => $this->data_type,
            'cardinality' => $this->cardinality,
            'is_indexed' => $this->is_indexed,
            'is_required' => $this->is_required,
            'ref_target_type' => $this->ref_target_type,
            'validation_rules' => $this->validation_rules,
            'ui_options' => $this->ui_options,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Флаги
            'is_materialized' => $this->source_component_id !== null,
            'is_ref' => $this->isRef(),
            'is_many' => $this->isMany(),
        ];
    }
}

