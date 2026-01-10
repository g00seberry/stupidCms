<?php

declare(strict_types=1);

namespace App\Services\DynamicRoutes\Registrars;

use App\Models\RouteNode;
use App\Services\DynamicRoutes\Validators\DynamicRouteValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Регистратор для GROUP узлов маршрутов.
 *
 * Регистрирует группы маршрутов через Route::group() с применением
 * атрибутов группы (prefix, domain, namespace, middleware, where)
 * и рекурсивной регистрацией дочерних узлов.
 *
 * @package App\Services\DynamicRoutes\Registrars
 */
class RouteGroupRegistrar extends AbstractRouteNodeRegistrar
{
    /**
     * Выполнить регистрацию группы маршрутов.
     *
     * @param \App\Models\RouteNode $node Узел группы
     * @return void
     */
    protected function doRegister(RouteNode $node): void
    {
        $attributes = $this->buildGroupAttributes($node);
        
        Log::debug('Dynamic route: регистрация группы маршрутов', [
            'route_node_id' => $node->id,
            'attributes' => $attributes,
        ]);

        Route::group($attributes, function () use ($node): void {
            $this->registerChildren($node);
        });
    }

    /**
     * Построить атрибуты для группы маршрутов.
     *
     * Собирает все атрибуты группы из RouteNode.
     *
     * @param \App\Models\RouteNode $node Узел группы
     * @return array<string, mixed> Атрибуты для Route::group()
     */
    private function buildGroupAttributes(RouteNode $node): array
    {
        $attributes = [];

        if ($node->prefix) {
            $attributes['prefix'] = $node->prefix;
        }

        if ($node->domain) {
            $attributes['domain'] = $node->domain;
        }

        if ($node->namespace) {
            $attributes['namespace'] = $node->namespace;
        }

        if (!empty($node->middleware)) {
            $attributes['middleware'] = $node->middleware;
        }

        if ($node->where) {
            $attributes['where'] = $node->where;
        }

        return $attributes;
    }
}

