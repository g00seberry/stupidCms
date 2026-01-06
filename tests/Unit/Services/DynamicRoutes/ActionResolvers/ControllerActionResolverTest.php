<?php

declare(strict_types=1);

namespace Tests\Unit\Services\DynamicRoutes\ActionResolvers;

use App\Enums\RouteNodeActionType;
use App\Models\RouteNode;
use App\Services\DynamicRoutes\ActionResolvers\ControllerActionResolver;
use App\Services\DynamicRoutes\DynamicRouteGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Тест для ControllerActionResolver.
 *
 * Проверяет резолвинг контроллеров с проверкой безопасности.
 */
class ControllerActionResolverTest extends TestCase
{
    use RefreshDatabase;

    private DynamicRouteGuard $guard;
    private ControllerActionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new DynamicRouteGuard();
        $this->resolver = new ControllerActionResolver($this->guard);
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
    }

    /**
     * Тест supports для Controller@method.
     */
    public function test_supports_controller_method(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
        ]);

        $this->assertTrue($this->resolver->supports($node));
    }

    /**
     * Тест supports для invokable контроллера.
     */
    public function test_supports_invokable_controller(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController',
        ]);

        $this->assertTrue($this->resolver->supports($node));
    }

    /**
     * Тест supports возвращает true для view: (обрабатывается ViewActionResolver в фабрике).
     *
     * Примечание: В реальной работе ActionResolverFactory проверяет ViewActionResolver
     * раньше ControllerActionResolver, поэтому этот случай не доходит до ControllerActionResolver.
     * Но в изоляции ControllerActionResolver может обработать любой CONTROLLER с action.
     */
    public function test_supports_returns_true_for_view_in_isolation(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'view:pages.about',
        ]);

        // В изоляции ControllerActionResolver вернет true, так как это CONTROLLER с action
        // В реальной работе это обработает ViewActionResolver раньше
        $this->assertTrue($this->resolver->supports($node));
    }

    /**
     * Тест supports возвращает true для redirect: (обрабатывается RedirectActionResolver в фабрике).
     *
     * Примечание: В реальной работе ActionResolverFactory проверяет RedirectActionResolver
     * раньше ControllerActionResolver, поэтому этот случай не доходит до ControllerActionResolver.
     * Но в изоляции ControllerActionResolver может обработать любой CONTROLLER с action.
     */
    public function test_supports_returns_true_for_redirect_in_isolation(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'redirect:/new-page',
        ]);

        // В изоляции ControllerActionResolver вернет true, так как это CONTROLLER с action
        // В реальной работе это обработает RedirectActionResolver раньше
        $this->assertTrue($this->resolver->supports($node));
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
     * Тест resolve для Controller@method.
     */
    public function test_resolve_controller_method(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@show',
        ]);

        $result = $this->resolver->resolve($node);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('App\\Http\\Controllers\\TestController', $result[0]);
        $this->assertEquals('show', $result[1]);
    }

    /**
     * Тест resolve для invokable контроллера.
     */
    public function test_resolve_invokable_controller(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController',
        ]);

        $result = $this->resolver->resolve($node);

        $this->assertIsString($result);
        $this->assertEquals('App\\Http\\Controllers\\TestController', $result);
    }

    /**
     * Тест resolve возвращает fallback для неразрешённого контроллера.
     */
    public function test_resolve_returns_fallback_for_disallowed_controller(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Forbidden\\Controller@show',
        ]);

        $result = $this->resolver->resolve($node);

        $this->assertIsCallable($result);
    }

    /**
     * Тест resolve возвращает fallback для несуществующего контроллера.
     */
    public function test_resolve_returns_fallback_for_nonexistent_controller(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\NonExistentController@show',
        ]);

        $result = $this->resolver->resolve($node);

        $this->assertIsCallable($result);
    }

    /**
     * Тест resolve возвращает fallback для несуществующего метода.
     */
    public function test_resolve_returns_fallback_for_nonexistent_method(): void
    {
        $node = RouteNode::factory()->route()->create([
            'action_type' => RouteNodeActionType::CONTROLLER,
            'action' => 'App\\Http\\Controllers\\TestController@nonexistent',
        ]);

        $result = $this->resolver->resolve($node);

        $this->assertIsCallable($result);
    }
}

