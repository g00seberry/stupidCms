<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\RouteNode;

/**
 * API Resource для RouteNode в админ-панели.
 *
 * Форматирует RouteNode для ответа API, включая связанные сущности
 * (entry, parent, children) при их загрузке.
 *
 * @package App\Http\Resources\Admin
 */
class RouteNodeResource extends AdminJsonResource
{
    /**
     * Преобразовать ресурс в массив.
     *
     * Возвращает массив с полями узла маршрута, включая:
     * - Основные поля (id, parent_id, sort_order, enabled, kind, name, domain, prefix, namespace)
     * - Поля маршрута (methods, uri, action_type, action, entry_id)
     * - JSON поля (middleware, where, defaults, options) преобразованные в объекты
     * - Связанные сущности (entry, parent, children) при их загрузке
     * - Даты в ISO 8601 формате
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return array<string, mixed> Массив данных узла маршрута
     */
    public function toArray($request): array
    {
        /** @var RouteNode $node */
        $node = $this->resource;

        return [
            'id' => $node->id,
            'parent_id' => $node->parent_id,
            'sort_order' => $node->sort_order,
            'enabled' => $node->enabled,
            'readonly' => $node->readonly ?? false,
            'kind' => $node->kind?->value ?? $node->getRawOriginal('kind'),
            'name' => $node->name,
            'domain' => $node->domain,
            'prefix' => $node->prefix,
            'namespace' => $node->namespace,
            'methods' => $node->methods,
            'uri' => $node->uri,
            'action_type' => $node->action_type?->value ?? $node->getRawOriginal('action_type'),
            'action' => $node->action,
            'entry_id' => $node->entry_id,
            'middleware' => $node->middleware,
            'where' => $node->where,
            'defaults' => $node->defaults,
            'options' => $node->options,
            'entry' => $this->when($node->relationLoaded('entry'), function () use ($node) {
                return $node->entry ? [
                    'id' => $node->entry->id,
                    'title' => $node->entry->title,
                    'status' => $node->entry->status,
                ] : null;
            }),
            'parent' => $this->when($node->relationLoaded('parent'), function () use ($node) {
                return $node->parent ? [
                    'id' => $node->parent->id,
                    'name' => $node->parent->name,
                    'kind' => $node->parent->kind->value,
                ] : null;
            }),
            'children' => $this->when($node->relationLoaded('children'), function () use ($node) {
                return RouteNodeResource::collection($node->children);
            }),
            'created_at' => $node->created_at?->toIso8601String(),
            'updated_at' => $node->updated_at?->toIso8601String(),
            'deleted_at' => $node->deleted_at?->toIso8601String(),
        ];
    }
}

