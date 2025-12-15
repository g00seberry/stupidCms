<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\View\TemplateResolver;
use App\Models\Entry;
use App\Models\RouteNode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Контроллер для отображения Entry через динамические маршруты.
 *
 * Используется для маршрутов с action_type='entry' (жёсткое назначение Entry на URL).
 * Возвращает Blade view с данными Entry согласно BladeTemplateResolver.
 *
 * @package App\Http\Controllers
 */
final class EntryPageController
{
    /**
     * @param \App\Domain\View\TemplateResolver $templateResolver Резолвер шаблонов
     */
    public function __construct(
        private readonly TemplateResolver $templateResolver,
    ) {}

    /**
     * Отобразить Entry по маршруту.
     *
     * Получает route_node_id из defaults маршрута, загружает RouteNode с Entry
     * и возвращает Blade view с данными Entry согласно приоритету шаблонов:
     * 1. Entry.template_override
     * 2. PostType.template
     * 3. templates.index (дефолтный)
     *
     * @param \Illuminate\Http\Request $request HTTP запрос
     * @return \Illuminate\Contracts\View\View Blade view с Entry
     */
    public function show(Request $request): View
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

        // Проверяем публикацию Entry
        if ($entry->status !== Entry::STATUS_PUBLISHED) {
            abort(404, 'Entry is not published');
        }

        if (!$entry->published_at || $entry->published_at->isFuture()) {
            abort(404, 'Entry is not published yet');
        }

        // Получаем имя шаблона через TemplateResolver
        $templateName = $this->templateResolver->forEntry($entry);

        // Возвращаем view с передачей Entry
        return view($templateName, [
            'entry' => $entry,
        ]);
    }
}

