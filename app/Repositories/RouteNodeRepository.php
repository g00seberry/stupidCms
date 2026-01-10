<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\RouteNode;
use App\Services\DynamicRoutes\DeclarativeRouteLoader;
use App\Services\DynamicRoutes\DynamicRouteCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RouteNodeRepository
{
    public function __construct(
        private DynamicRouteCache $cache,
        private ?DeclarativeRouteLoader $declarativeLoader = null,
    ) {}

    public function getTree(): Collection
    {
        return $this->cache->rememberTree(
            fn () => $this->mergedTree(enabledOnly: false, catchDbErrors: false)
        );
    }

    public function getEnabledTree(): Collection
    {
        return $this->cache->rememberTree(
            fn () => $this->mergedTree(enabledOnly: true)
        );
    }

    public function getDeclarativeTree(): Collection
    {
        return $this->cache->rememberDeclarativeTree(
            fn () => $this->loadDeclarativeTree()
        );
    }

    public function getDynamicTree(): Collection
    {
        return $this->cache->rememberDynamicTree(
            fn () => $this->loadDynamicTree()
        );
    }

    public function loadDeclarativeTree(): Collection
    {
        return $this->declarativeLoader?->loadAll() ?? new Collection();
    }

    public function getNodeWithAncestors(int $id): ?RouteNode
    {
        try {
            return $this->getNodeWithAncestorsCte($id);
        } catch (Throwable) {
            return $this->getNodeWithAncestorsLoop($id);
        }
    }

    private function mergedTree(bool $enabledOnly, bool $catchDbErrors = true): Collection
    {
        $dbRoots = $this->buildTreeFromNodes(
            $this->loadNodesFromDatabase($enabledOnly, $catchDbErrors)
        );

        return $this->loadDeclarativeTree()
            ->merge($dbRoots)
            ->sortBy('sort_order')
            ->values();
    }

    private function loadDynamicTree(): Collection
    {
        return $this->buildTreeFromNodes(
            $this->loadNodesFromDatabase(enabledOnly: true)
        )->sortBy('sort_order')->values();
    }

    private function loadNodesFromDatabase(bool $enabledOnly, bool $catchDbErrors = true): Collection
    {
        $query = RouteNode::query()
            ->when($enabledOnly, fn ($q) => $q->where('enabled', true))
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (!$catchDbErrors) {
            return $query->get();
        }

        try {
            return $query->get();
        } catch (Throwable) {
            return new Collection();
        }
    }

    private function buildTreeFromNodes(Collection $nodes): Collection
    {
        $byId = $nodes->keyBy('id');
        $roots = new Collection();

        foreach ($nodes as $node) {
            if ($node->parent_id === null) {
                $roots->push($node);
                continue;
            }

            $parent = $byId->get($node->parent_id);
            if (!$parent) {
                continue;
            }

            if (!$parent->relationLoaded('children')) {
                $parent->setRelation('children', new Collection());
            }

            $parent->children->push($node);
        }

        return $roots;
    }

    private function getNodeWithAncestorsCte(int $id): ?RouteNode
    {
        $table = (new RouteNode())->getTable();
        $t = DB::getQueryGrammar()->wrapTable($table);

        $rows = DB::select(
            "WITH RECURSIVE a AS (
                SELECT * FROM {$t} WHERE id = ?
                UNION ALL
                SELECT p.* FROM {$t} p
                JOIN a ON p.id = a.parent_id
            )
            SELECT * FROM a",
            [$id],
        );

        if (!$rows) {
            return null;
        }

        $models = RouteNode::hydrate(array_map(static fn ($r) => (array) $r, $rows));
        $byId = $models->keyBy('id');

        foreach ($models as $m) {
            if ($m->parent_id !== null && $byId->has($m->parent_id)) {
                $m->setRelation('parent', $byId->get($m->parent_id));
            }
        }

        return $byId->get($id);
    }

    private function getNodeWithAncestorsLoop(int $id): ?RouteNode
    {
        $node = RouteNode::find($id);
        if (!$node) {
            return null;
        }

        $current = $node;
        while ($current->parent_id !== null) {
            $parent = RouteNode::find($current->parent_id);
            if (!$parent) {
                break;
            }
            $current->setRelation('parent', $parent);
            $current = $parent;
        }

        return $node;
    }
}
