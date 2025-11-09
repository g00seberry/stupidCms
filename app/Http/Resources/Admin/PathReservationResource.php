<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class PathReservationResource extends AdminJsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'path' => $this->path,
            'kind' => $this->kind,
            'source' => $this->source,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}


