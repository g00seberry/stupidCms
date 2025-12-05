<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

/**
 * API Resource для BlueprintEmbed в админ-панели.
 *
 * Форматирует BlueprintEmbed для ответа API, включая связанные сущности
 * (blueprint, embeddedBlueprint, hostPath) при их загрузке.
 *
 * @mixin \App\Models\BlueprintEmbed
 * @package App\Http\Resources\Admin
 */
class BlueprintEmbedResource extends AdminJsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Возвращает массив с полями embed, включая:
     * - Основные поля (id, blueprint_id, embedded_blueprint_id, host_path_id)
     * - Связанные сущности (blueprint, embedded_blueprint, host_path) при их загрузке
     * - Даты в ISO 8601 формате
     *
     * @param Request $request HTTP запрос
     * @return array<string, mixed> Массив данных embed
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'blueprint_id' => $this->blueprint_id,
            'embedded_blueprint_id' => $this->embedded_blueprint_id,
            'host_path_id' => $this->host_path_id,

            // Связи
            'blueprint' => $this->whenLoaded('blueprint', fn() => [
                'id' => $this->blueprint->id,
                'code' => $this->blueprint->code,
                'name' => $this->blueprint->name,
            ]),

            'embedded_blueprint' => $this->whenLoaded('embeddedBlueprint', fn() => [
                'id' => $this->embeddedBlueprint->id,
                'code' => $this->embeddedBlueprint->code,
                'name' => $this->embeddedBlueprint->name,
            ]),

            'host_path' => $this->whenLoaded('hostPath', function () {
                return $this->hostPath ? [
                    'id' => $this->hostPath->id,
                    'name' => $this->hostPath->name,
                    'full_path' => $this->hostPath->full_path,
                ] : null;
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

