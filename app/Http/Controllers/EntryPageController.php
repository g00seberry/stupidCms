<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\RouteNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Контроллер для отображения Entry через динамические маршруты.
 *
 * Используется для маршрутов с action_type='entry' (жёсткое назначение Entry на URL).
 * Возвращает JSON с данными Entry в headless режиме.
 *
 * @package App\Http\Controllers
 */
final class EntryPageController
{
    /**
     * Отобразить Entry по маршруту.
     *
     * Получает route_node_id из defaults маршрута, загружает RouteNode с Entry
     * и возвращает JSON с данными Entry, PostType, Blueprint и Route.
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return \Illuminate\Http\JsonResponse JSON ответ с данными Entry
     */
    public function show(Request $request): JsonResponse
    {
        // Получаем route_node_id из defaults маршрута
        $routeNodeId = $request->route()?->defaults['route_node_id'] ?? null;

        if (!$routeNodeId) {
            abort(404, 'Route node ID not found');
        }

        // Загружаем RouteNode с Entry и связанными данными
        $routeNode = RouteNode::with(['entry.postType.blueprint'])
            ->find($routeNodeId);

        if (!$routeNode) {
            abort(404, 'Route node not found');
        }

        // Проверяем наличие entry_id
        if (!$routeNode->entry_id) {
            abort(404, 'Entry not assigned to route');
        }

        $entry = $routeNode->entry;

        if (!$entry) {
            abort(404, 'Entry not found');
        }

        // Проверяем публикацию (по умолчанию только published)
        $requirePublished = $routeNode->options['require_published'] ?? true;

        if ($requirePublished) {
            // Проверяем статус и дату публикации
            if ($entry->status !== Entry::STATUS_PUBLISHED) {
                abort(404, 'Entry is not published');
            }

            if (!$entry->published_at || $entry->published_at->isFuture()) {
                abort(404, 'Entry is not published yet');
            }
        }

        // Формируем ответ
        return response()->json([
            'entry' => [
                'id' => $entry->id,
                'title' => $entry->title,
                'status' => $entry->status,
                'published_at' => $entry->published_at?->toIso8601String(),
                'data_json' => $entry->data_json,
                'template_override' => $entry->template_override,
                'created_at' => $entry->created_at->toIso8601String(),
                'updated_at' => $entry->updated_at->toIso8601String(),
            ],
            'post_type' => $entry->postType ? [
                'id' => $entry->postType->id,
                'name' => $entry->postType->name,
                'template' => $entry->postType->template,
            ] : null,
            'blueprint' => $entry->postType?->blueprint ? [
                'id' => $entry->postType->blueprint->id,
                'name' => $entry->postType->blueprint->name,
            ] : null,
            'route' => [
                'id' => $routeNode->id,
                'uri' => $routeNode->uri,
                'name' => $routeNode->name,
            ],
        ]);
    }
}

