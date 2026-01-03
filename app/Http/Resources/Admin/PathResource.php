<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Services\Path\Constraints\PathConstraintsBuilderRegistry;
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
     * - Метаданные (data_type, cardinality, is_indexed)
     * - Правила валидации (validation_rules, содержащий required)
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
            'is_indexed' => (bool) $this->is_indexed,
            'validation_rules' => $this->validation_rules,

            // Constraints для полей
            'constraints' => $this->when($this->hasConstraints(), function () {
                return $this->getConstraintsArray();
            }),

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

    /**
     * Проверить, есть ли у path constraints.
     *
     * Использует регистр билдеров для проверки наличия constraints.
     *
     * @return bool true, если есть constraints
     */
    private function hasConstraints(): bool
    {
        $registry = $this->getConstraintsBuilderRegistry();
        $builder = $registry->getBuilder($this->data_type);

        if ($builder !== null) {
            return $builder->hasConstraints($this->resource);
        }

        return false;
    }

    /**
     * Получить массив constraints для ресурса.
     *
     * Использует регистр билдеров для построения constraints.
     *
     * @return array<string, mixed> Массив constraints
     */
    private function getConstraintsArray(): array
    {
        $registry = $this->getConstraintsBuilderRegistry();
        $builder = $registry->getBuilder($this->data_type);

        if ($builder !== null) {
            return $builder->buildForResource($this->resource);
        }

        return [];
    }

    /**
     * Получить регистр билдеров constraints.
     *
     * @return PathConstraintsBuilderRegistry
     */
    private function getConstraintsBuilderRegistry(): PathConstraintsBuilderRegistry
    {
        return app(PathConstraintsBuilderRegistry::class);
    }
}

