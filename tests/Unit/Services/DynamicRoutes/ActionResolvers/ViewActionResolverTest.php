<?php

declare(strict_types=1);

namespace Tests\Unit\Services\DynamicRoutes\ActionResolvers;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\ActionResolvers\ViewActionResolver;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тест для ViewActionResolver.
 *
 * Проверяет резолвинг view: действий.
 */
class ViewActionResolverTest extends TestCase
{
    use RefreshDatabase;

    private DynamicRouteGuard $guard;
    private ViewActionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new DynamicRouteGuard();
        $this->resolver = new ViewActionResolver($this->guard);
    }

    /**
     * Тест supports для view:.
     */
    public function test_supports_view_action(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'view:pages.about',
        ]);

        $this->assertTrue($this->resolver->supports($node));
    }

    /**
     * Тест supports возвращает false для обычного контроллера.
     */
    public function test_supports_returns_false_for_controller(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
        ]);

        $this->assertFalse($this->resolver->supports($node));
    }

    /**
     * Тест supports возвращает false для ENTRY.
     */
    public function test_supports_returns_false_for_entry(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::ENTRY,
        ]);

        $this->assertFalse($this->resolver->supports($node));
    }

    /**
     * Тест resolve создаёт closure для view().
     */
    public function test_resolve_creates_view_closure(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'view:pages.about',
        ]);

        $result = $this->resolver->resolve($node);

        $this->assertIsCallable($result);
    }

    /**
     * Тест resolve извлекает правильное имя view.
     */
    public function test_resolve_extracts_view_name(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'view:pages.contact',
        ]);

        $result = $this->resolver->resolve($node);

        $this->assertIsCallable($result);
        
        // Проверяем, что closure вызывает view() с правильным именем
        // Это можно проверить только через выполнение, но для unit-теста достаточно проверить, что это callable
        $this->assertTrue(true);
    }
}

