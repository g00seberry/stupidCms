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
     * - Дочерние поля (children) - присутствует только если есть дочерние элементы
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

            // Дочерние поля (только если есть реальные дочерние элементы)
            'children' => $this->when($this->hasChildren(), function () {
                return $this->getChildrenCollection();
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Проверить, есть ли у path дочерние элементы.
     *
     * Проверяет, загружены ли children через отношение или установлены вручную (через buildTree),
     * и есть ли в них реальные элементы.
     *
     * @return bool true, если есть дочерние элементы
     */
    private function hasChildren(): bool
    {
        // Если children загружены через отношение, проверяем количество
        if ($this->relationLoaded('children')) {
            return $this->children->isNotEmpty();
        }
        
        // Если children установлены вручную (через buildTree), проверяем коллекцию
        $children = $this->children;
        if ($children instanceof \Illuminate\Support\Collection || $children instanceof \Illuminate\Database\Eloquent\Collection) {
            return $children->isNotEmpty();
        }
        
        // Если children не установлены (вернулось отношение), считаем что их нет
        return false;
    }

    /**
     * Получить коллекцию children для ресурса.
     *
     * Проверяет, загружены ли children через отношение или установлены вручную (через buildTree).
     * Возвращает коллекцию PathResource.
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
        // (этот случай не должен произойти, так как hasChildren() вернёт false)
        return PathResource::collection(collect());
    }
}

