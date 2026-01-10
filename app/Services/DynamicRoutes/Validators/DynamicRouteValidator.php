<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Validators;

use App\Enums\RouteNodeKind;
use App\Models\RouteNode;
use App\Repositories\RouteNodeRepository;
use App\Services\DynamicRoutes\RoutePathBuilder;
use App\Services\DynamicRoutes\RoutePatternNormalizer;
use Illuminate\Database\Eloquent\Collection;

final class DynamicRouteValidator
{
    private RoutePatternNormalizer $patterns;
    private RoutePathBuilder $paths;

    public function __construct(
        private RouteNodeRepository $repository,
        ?RoutePatternNormalizer $patterns = null,
        ?RoutePathBuilder $paths = null,
    ) {
        $this->patterns = $patterns ?? new RoutePatternNormalizer();
        $this->paths = $paths ?? new RoutePathBuilder();
    }

    public function isPrefixReserved(string $prefix): bool
    {
        $prefix = trim($prefix, '/');

        foreach (config('dynamic-routes.reserved_prefixes', []) as $r) {
            $r = trim((string) $r, '/');
            if ($r !== '' && ($prefix === $r || str_starts_with($prefix, $r . '/'))) {
                return true;
            }
        }

        return false;
    }

    public function checkConflict(string $uri, array $methods, ?int $excludeId = null, ?int $parentId = null): ?RouteNode
    {
        return $this->findConflictWithPath(
            $this->repository->getTree(),
            $this->fullPathForNewRoute($uri, $parentId),
            $this->normalizeMethods($methods),
            $excludeId
        )['node'] ?? null;
    }

    /**
     * @return array{allowed: bool, reason: string|null, conflicting_route: RouteNode|null}
     */
    public function canCreateRoute(string $uri, array $methods, ?int $excludeId = null, ?int $parentId = null): array
    {
        $fullPath = $this->fullPathForNewRoute($uri, $parentId);
        $firstSegment = explode('/', $fullPath, 2)[0] ?? '';

        if ($firstSegment !== '' && $this->isPrefixReserved($firstSegment)) {
            return [
                'allowed' => false,
                'reason' => "Префикс '{$firstSegment}' зарезервирован для системных маршрутов",
                'conflicting_route' => null,
            ];
        }

        $result = $this->findConflictWithPath(
            $this->repository->getTree(),
            $fullPath,
            $this->normalizeMethods($methods),
            $excludeId
        );

        if ($result !== null) {
            $node = $result['node'];
            $source = ($node->id ?? 0) < 0 ? 'декларативный файл' : 'БД';

            return [
                'allowed' => false,
                'reason' => "Маршрут с полным путем '{$result['path']}' и методами [" . implode(', ', $methods) . "] уже существует в {$source}",
                'conflicting_route' => $node,
            ];
        }

        return ['allowed' => true, 'reason' => null, 'conflicting_route' => null];
    }

    private function fullPathForNewRoute(string $uri, ?int $parentId): string
    {
        $uri = ltrim($uri, '/');

        if ($parentId === null) {
            return $uri;
        }

        $parent = $this->repository->getNodeWithAncestors($parentId);

        $tmp = new RouteNode();
        $tmp->kind = RouteNodeKind::ROUTE;
        $tmp->uri = $uri;
        $tmp->setRelation('parent', $parent);

        return $this->paths->buildFullPath($tmp);
    }

    /**
     * @param array<string> $methods
     * @return array<string>
     */
    private function normalizeMethods(array $methods): array
    {
        $methods = array_map(static fn ($m) => strtoupper((string) $m), $methods);
        $methods = array_values(array_filter($methods, static fn ($m) => $m !== ''));

        return array_values(array_unique($methods));
    }

    /**
     * @return array{node: RouteNode, path: string}|null
     */
    private function findConflictWithPath(
        Collection $nodes,
        string $targetPath,
        array $targetMethods,
        ?int $excludeId
    ): ?array {
        $targetPath = trim($targetPath, '/');

        foreach ($nodes as $node) {
            if ($excludeId !== null && $node->id === $excludeId) {
                continue;
            }

            if ($node->kind === RouteNodeKind::GROUP) {
                if ($node->relationLoaded('children') && $node->children) {
                    $hit = $this->findConflictWithPath($node->children, $targetPath, $targetMethods, $excludeId);
                    if ($hit) {
                        return $hit;
                    }
                }
                continue;
            }
            $nodePath = $this->paths->buildFullPath($node);
           
            if (!$this->patterns->patternsConflict($nodePath, $targetPath)) {
                continue;
            }

            $nodeMethods = $this->normalizeMethods((array) $node->methods);
            if (array_intersect($nodeMethods, $targetMethods)) {
                return ['node' => $node, 'path' => $nodePath];
            }
        }

        return null;
    }
}
