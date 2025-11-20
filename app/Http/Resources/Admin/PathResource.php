<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

/**
 * API Resource для Path в админ-панели.
 *
 * Форматирует Path для ответа API, включая связанные сущности
 * (blueprint, parent, children, sourceBlueprint, blueprintEmbed) при их загрузке.
 *
 * @mixin \App\Models\Path
 * @package App\Http\Resources\Admin
 */
class PathResource extends AdminJsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Возвращает массив с полями path, включая:
     * - Основные поля (id, blueprint_id, parent_id, name, full_path)
     * - Метаданные (data_type, cardinality, is_required, is_indexed, is_readonly, sort_order)
     * - Правила валидации (validation_rules)
     * - Источник копии (source_blueprint_id, source_blueprint, blueprint_embed_id)
     * - Дочерние поля (children) - всегда присутствует (может быть пустым массивом)
     * - Даты в ISO 8601 формате
     *
     * @param Request $request HTTP запрос
     * @return array<string, mixed> Массив данных path
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'blueprint_id' => $this->blueprint_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'full_path' => $this->full_path,
            'data_type' => $this->data_type,
            'cardinality' => $this->cardinality,
            'is_required' => (bool) $this->is_required,
            'is_indexed' => (bool) $this->is_indexed,
            'is_readonly' => (bool) $this->is_readonly,
            'sort_order' => $this->sort_order,
            'validation_rules' => $this->validation_rules,

            // Источник копии (если копия)
            'source_blueprint_id' => $this->source_blueprint_id,
            'source_blueprint' => $this->whenLoaded('sourceBlueprint', function () {
                return [
                    'id' => $this->sourceBlueprint->id,
                    'code' => $this->sourceBlueprint->code,
                    'name' => $this->sourceBlueprint->name,
                ];
            }),

            // Embed (если копия)
            'blueprint_embed_id' => $this->blueprint_embed_id,

            // Дочерние поля (если загружены или установлены вручную через buildTree)
            'children' => $this->getChildrenCollection(),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Получить коллекцию children для ресурса.
     *
     * Проверяет, загружены ли children через отношение или установлены вручную (через buildTree).
     * Всегда возвращает коллекцию PathResource (может быть пустой).
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    private function getChildrenCollection(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        // Если children загружены через отношение, используем их
        if ($this->relationLoaded('children')) {
            return PathResource::collection($this->children);
        }
        
        // Если children установлены вручную (через buildTree), используем их
        // Проверяем, является ли children коллекцией (установлено вручную)
        $children = $this->children;
        if ($children instanceof \Illuminate\Support\Collection || $children instanceof \Illuminate\Database\Eloquent\Collection) {
            return PathResource::collection($children);
        }
        
        // Если children не установлены (вернулось отношение), возвращаем пустую коллекцию
        return PathResource::collection(collect());
    }
}

