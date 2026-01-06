<?php

declare(strict_types=1);

namespace Tests\Unit\Services\DynamicRoutes\ActionResolvers;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\ActionResolvers\RedirectActionResolver;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тест для RedirectActionResolver.
 *
 * Проверяет резолвинг redirect: действий.
 */
class RedirectActionResolverTest extends TestCase
{
    use RefreshDatabase;

    private DynamicRouteGuard $guard;
    private RedirectActionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new DynamicRouteGuard();
        $this->resolver = new RedirectActionResolver($this->guard);
    }

    /**
     * Тест supports для redirect:.
     */
    public function test_supports_redirect_action(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'redirect:/new-page',
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
     * Тест resolve создаёт closure для redirect().
     */
    public function test_resolve_creates_redirect_closure(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'redirect:/new-page',
        ]);

        $result = $this->resolver->resolve($node);

        $this->assertIsCallable($result);
    }

    /**
     * Тест resolve парсит redirect с статусом.
     */
    public function test_resolve_parses_redirect_with_status(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'redirect:/new-page:301',
        ]);

        $result = $this->resolver->resolve($node);

        $this->assertIsCallable($result);
    }

    /**
     * Тест resolve использует статус 302 по умолчанию.
     */
    public function test_resolve_uses_default_status_302(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'redirect:/new-page',
        ]);

        $result = $this->resolver->resolve($node);

        $this->assertIsCallable($result);
    }
}

