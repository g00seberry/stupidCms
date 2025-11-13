<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

/**
 * API Resource для PathReservation в админ-панели.
 *
 * Форматирует резервацию пути для ответа API.
 *
 * @package App\Http\Resources\Admin
 */
class PathReservationResource extends AdminJsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив с полями резервации пути
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


