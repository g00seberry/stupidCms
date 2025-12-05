<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

/**
 * API Resource для Blueprint в админ-панели.
 *
 * Форматирует Blueprint для ответа API, включая связанные сущности
 * (paths, embeds, postTypes) при их загрузке.
 *
 * @mixin \App\Models\Blueprint
 * @package App\Http\Resources\Admin
 */
class BlueprintResource extends AdminJsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Возвращает массив с полями blueprint, включая:
     * - Основные поля (id, name, code, description)
     * - Счётчики (paths_count, embeds_count, embedded_in_count, post_types_count)
     * - Связанные сущности (post_types) при их загрузке
     * - Даты в ISO 8601 формате
     *
     * @param Request $request HTTP запрос
     * @return array<string, mixed> Массив данных blueprint
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,

            // Счётчики (если загружены)
            'paths_count' => $this->whenCounted('paths'),
            'embeds_count' => $this->whenCounted('embeds'),
            'embedded_in_count' => $this->whenCounted('embeddedIn'),
            'post_types_count' => $this->whenCounted('postTypes'),

            // Связи
            'post_types' => $this->whenLoaded('postTypes', function () {
                return $this->postTypes->map(fn($pt) => [
                    'id' => $pt->id,
                    'slug' => $pt->slug,
                    'name' => $pt->name,
                ]);
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

